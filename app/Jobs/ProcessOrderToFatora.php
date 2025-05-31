<?php

namespace App\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Admin\Http\Services\UblInvoiceService;
use Modules\Shop\Entities\Order;

class ProcessOrderToFatora implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $userId;

    public function __construct(Order $order, $userId)
    {
        $this->order = $order;
        $this->userId = $userId;
    }

    public function handle(UblInvoiceService $service)
    {
        set_time_limit(300);
        try {
            $orderToFatora = $this->calcOrderFatora($this->order);

            // 1. Generate XML
            $xml = $service->generate($orderToFatora);
            $payload = $service->prepareForSubmission($xml);

            $response = Http::withHeaders([
                'Client-Id' => config('jo_fotara.client_id'),
                'Secret-Key' => config('jo_fotara.secret_key'),
                'Content-Type' => 'application/json',
            ])->post(config('jo_fotara.api_url').'/core/invoices/', $payload);

            $oldOrder = Order::find($this->order->id);
            // Handle Response
            if ($response->successful()) {
                $responseData = $response->json();

                $oldOrder->update([
                    'qr_code' => $responseData['EINV_QR'],
                    'fatora_status' => $responseData['EINV_STATUS'],
                    'is_migrated' => true,
                    'migrated_at' => now(),
                    'migrated_by' => $this->userId
                ]);

                Log::info("Order {$this->order->id} successfully migrated to Fatora");
            } else {
                $errorCode = $response->json('errorCode');
                $responseData = $response->json();

                $oldOrder->update([
                    'fatora_status' => $responseData['EINV_RESULTS']['EINV_STATUS'] ?? 'failed',
                    'migrate_error' => $responseData['EINV_RESULTS']['ERRORS'] ?? $this->mapErrorCode($errorCode),
                    'is_migrated' => false,
                ]);

                Log::error("Failed to migrate order {$this->order->id} to Fatora", [
                    'error' => $responseData['EINV_RESULTS']['ERRORS'] ?? $response->body(),
                    'code' => $errorCode
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Exception while processing order {$this->order->id}: " . $e->getMessage());

            $oldOrder->update([
                'fatora_status' => 'failed',
                'migrate_error' => $e->getMessage(),
                'is_migrated' => false,
            ]);

            // Optionally release the job to try again later
            // $this->release(30); // 30 seconds delay
        }
    }

    private function mapErrorCode($code)
    {
        // Map error codes from documentation
        $errors = [
            'E001' => 'Invalid client credentials',
            'E002' => 'Invalid XML structure',
            'E003' => 'Duplicate invoice submission',
            // Add more codes from documentation
        ];

        return $errors[$code] ?? 'Unknown error';
    }

    private function calcOrderFatora(Order $order)
    {

        $is_taxed = $order->options->taxed;
        $is_exempt = $order->options->tax_exempt;
        $tax_zero = $order->options->tax_zero;
        $taxChar = $this->tax($is_taxed,$is_exempt,$tax_zero);
        $taxValue = ($taxChar == 'S') ? 0.16 : 0;
        $totalTax = ($order->total / (1 + $taxValue)) * $taxValue;
        $totalBeforDiscount =$order->subtotal - ($order->subtotal / (1 + $taxValue)) * $taxValue;
        $totalAfterDiscountAndTax = $order->total;
        $fixedOrder = $order;
        $fixedOrder->tax_char = $taxChar;
        $fixedOrder->tax_value = $taxValue;
        $fixedOrder->totalTax = $totalTax;
        $fixedOrder->totalBeforDiscount = $totalBeforDiscount;
        $fixedOrder->totalAfterDiscountAndTax = $totalAfterDiscountAndTax;

        $fixedOrder->final_discount = $this->calcFinalDiscount($order,$taxValue);
        $fixedOrder->final_tax = $this->calcFinalTax($order,$taxValue);
        $fixedOrder->final_total = $this->calcFinalTotal($order,$taxValue);

        return $fixedOrder;



    }

    private function tax($is_taxed,$is_exempt,$tax_zero)
    {
        if ($is_taxed && !$is_exempt && !$tax_zero){
            return 'S';
        }elseif ($is_taxed && $is_exempt && !$tax_zero){
            return 'Z';
        }elseif ($is_taxed && $is_exempt && $tax_zero){
            return 'O';
        }else{
            return null;
        }

    }

    private function calcFinalDiscount($order,$taxValue)
    {
        $discount = 0;
        foreach ($order->products as $product){
            $discount += number_format($product->pivot->discount / (1+$taxValue),3, '.', '');
        }
        if ($order->extra_items != null && count($order->extra_items) > 0){
            foreach ($order->extra_items as $product){
                $discount += number_format($product->discount / (1+$taxValue),3, '.', '');
            }
        }

        return $discount;
    }

    private function calcFinalTax($order,$taxValue)
    {
        $tax = 0;
        foreach ($order->products as $product){
            $tax += number_format((($product->pivot->quantity *number_format(($product->pivot->price /(1+$taxValue)), 3, '.', '') ) - (number_format(($product->pivot->discount /(1+$taxValue)),3, '.', ''))) * $taxValue,3, '.', '');
        }
        if ($order->extra_items != null && count($order->extra_items) > 0){
            foreach ($order->extra_items as $product){
                $tax += number_format((($product->quantity *($product->price /(1+$taxValue)) ) - (number_format(($product->discount /(1+$taxValue)),3, '.', ''))) * $taxValue,3, '.', '');
            }
        }

        return $tax;
    }

    private function calcFinalTotal($order,$taxValue)
    {
        $total = 0;
        foreach ($order->products as $product){
            $total += number_format((number_format(($product->pivot->price / (1+$taxValue)), 3, '.', '') * $product->pivot->quantity), 3, '.', '');
        }
        if ($order->extra_items != null && count($order->extra_items) > 0){
            foreach ($order->extra_items as $product){
                $total += number_format((number_format(($product->price / (1+$taxValue)), 3, '.', '') * $product->quantity), 3, '.', '');
            }
        }

        return $total;
    }
}
