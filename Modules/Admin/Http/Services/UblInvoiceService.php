<?php

namespace Modules\Admin\Http\Services;

use Carbon\Carbon;
use Modules\Shop\Entities\Order;
use DOMDocument;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Log;

class UblInvoiceService
{
    public function generate(Order $orderData): string
    {
        // Generate XML with proper declaration and encoding
        $xmlContent = view('ubl_invoice', [
            'order' => $orderData,
            'uuid' => $this->generateUuid(),
            'issueDate' => Carbon::now()->format('Y-m-d')
        ])->render();

        // Ensure proper XML formatting
        return $this->formatXml($xmlContent);
    }

    private function generateUuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    public function prepareForSubmission(string $xml): array
    {
        // First validate the XML structure
//        $validation = $this->validateXml($xml);
//        if (!$validation['valid']) {
//            throw new \RuntimeException('Invalid XML: ' . json_encode($validation['errors']));
//        }

        // Minify carefully without breaking XML structure
        $minifiedXml = $this->minifyXml($xml);

        // Log for debugging
        Log::debug('Minified XML:', [$minifiedXml]);

        return [
            'invoice' => base64_encode($minifiedXml)
        ];
    }

    private function minifyXml(string $xml): string
    {
        // First normalize all whitespace
        $xml = preg_replace('/\s+/', ' ', $xml);

        // Remove spaces between tags carefully
        $xml = preg_replace('/>\s+</', '><', $xml);

        // Trim and ensure XML declaration remains
        $xml = trim($xml);

        // Ensure XML declaration is present
        if (strpos($xml, '<?xml') !== 0) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . $xml;
        }

        return $xml;
    }

    private function formatXml(string $xml): string
    {
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);

        return $dom->saveXML();
    }

//    public function validateXml(string $xml): array
//    {
//        libxml_use_internal_errors(true);
//
//        $dom = new DOMDocument();
//        $dom->loadXML($xml);
//
//        // Validate against UBL schema
//        $xsdPath = base_path('resources/xsd/UBL-Invoice-2.1.xsd');
//        $valid = @$dom->schemaValidate($xsdPath);
//
//        $errors = libxml_get_errors();
//        libxml_clear_errors();
//
//        return [
//            'valid' => $valid,
//            'errors' => array_map(function($error) {
//                return [
//                    'level' => $this->errorLevelToString($error->level),
//                    'message' => trim($error->message),
//                    'line' => $error->line
//                ];
//            }, $errors)
//        ];
//    }
//
//    private function errorLevelToString(int $level): string
//    {
//        return match($level) {
//        LIBXML_ERR_WARNING => 'Warning',
//            LIBXML_ERR_ERROR => 'Error',
//            LIBXML_ERR_FATAL => 'Fatal Error',
//            default => 'Unknown',
//        };
//    }
}
