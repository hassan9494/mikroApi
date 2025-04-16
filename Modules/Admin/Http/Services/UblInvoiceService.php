<?php

namespace Modules\Admin\Http\Services;

use Carbon\Carbon;
use Modules\Shop\Entities\Order;

class UblInvoiceService
{
    public function generate(Order $orderData): string
    {
        return view('ubl_invoice', [
            'order' => $orderData,
            'uuid' => $this->generateUuid(),
            'issueDate' => Carbon::now()->format('Y-m-d')
        ])->render();
    }

    private function generateUuid(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    public function wrapInJson(string $xml): string
    {
        $hash = hash('sha256', $xml);

        return json_encode([
            'invoice' => [
                'uuid' => $this->generateUuid(),
                'hash' => "sha256:$hash",
                'content' => base64_encode($xml)
            ]
        ]);
    }

    public function validateXml(string $xml): bool
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        return $dom->schemaValidate(resource_path('xsd/UBL-Invoice-2.1.xsd'));
    }
}
