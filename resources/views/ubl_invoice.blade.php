<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">


<cbc:ProfileID>reporting:1.0</cbc:ProfileID>
    <cbc:ID>{{ $order->tax_number }}</cbc:ID>
    <cbc:UUID>{{ $order->uuid }}</cbc:UUID>
    <cbc:IssueDate>{{ \Carbon\Carbon::parse($order->taxed_at)->format('Y-m-d') }}</cbc:IssueDate>
    <cbc:InvoiceTypeCode name="{{$order->options->dept ? '022' : '012' }}">388</cbc:InvoiceTypeCode>
    @if($order->invoice_notes)
        <cbc:Note>{{ htmlspecialchars($order->invoice_notes) }}</cbc:Note>
    @endif
    <cbc:DocumentCurrencyCode>{{ config('jo_fotara.currency') }}</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>{{ config('jo_fotara.currency') }}</cbc:TaxCurrencyCode>
    <cac:AdditionalDocumentReference>
        <cbc:ID>ICV</cbc:ID>
        <cbc:UUID>{{ $order->uuid }}</cbc:UUID>
    </cac:AdditionalDocumentReference>

    <!-- Seller Information -->
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PostalAddress>
                <cac:Country>
                    <cbc:IdentificationCode>JO</cbc:IdentificationCode>
                </cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>{{config('jo_fotara.company_id')}}</cbc:CompanyID>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>{{ config('jo_fotara.seller_name') }}</cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingSupplierParty>


    <!-- Buyer Information -->
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="{{$order->identity_number_type}}">{{$order->customer_identity_number ?? '000000000'}}</cbc:ID>
            </cac:PartyIdentification>
            <cac:PostalAddress>
                <cbc:PostalZone>{{ config('jo_fotara.postal_code') }}</cbc:PostalZone>
                <cbc:CountrySubentityCode>{{ config('jo_fotara.buyer_city') }}</cbc:CountrySubentityCode>
                <cac:Country>
                    <cbc:IdentificationCode>JO</cbc:IdentificationCode>
                </cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>1</cbc:CompanyID>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>{{$order->customer->name}}</cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
        <cac:AccountingContact>
            <cbc:Telephone> {{$order->customer->phone}}</cbc:Telephone>
        </cac:AccountingContact>
    </cac:AccountingCustomerParty>

    <!-- seller Income Source -->
    <cac:SellerSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID>{{ config('jo_fotara.seller_income_source') }}</cbc:ID>
            </cac:PartyIdentification>
        </cac:Party>
    </cac:SellerSupplierParty>

    <!-- order total -->
    <cac:AllowanceCharge>
        <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
        <cbc:AllowanceChargeReason>discount</cbc:AllowanceChargeReason>
        <cbc:Amount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format(($order->discount / ($order->tax_value + 1)), 4, '.', '')}}</cbc:Amount>
    </cac:AllowanceCharge>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format($order->totalTax, 4, '.', '') }}</cbc:TaxAmount>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:TaxExclusiveAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($order->totalBeforDiscount, 4, '.', '')}}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($order->totalAfterDiscountAndTax, 4, '.', '')}}</cbc:TaxInclusiveAmount>
        <cbc:AllowanceTotalAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format(($order->discount / ($order->tax_value + 1)), 4, '.', '')}}</cbc:AllowanceTotalAmount>
        <cbc:PayableAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($order->totalAfterDiscountAndTax, 4, '.', '')}}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>


    <!-- Line Items -->
    @foreach($order->products as $key=>$item)
        <cac:InvoiceLine>
            <cbc:ID>{{ $key + 1 }}</cbc:ID>
            <cbc:InvoicedQuantity unitCode="PCE">{{ $item->pivot->quantity }}</cbc:InvoicedQuantity>
            <cbc:LineExtensionAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format((($item->pivot->quantity * ($item->pivot->price / (1 + $order->tax_value))) - ($item->pivot->discount / (1+$order->tax_value))), 4, '.', '')  }}</cbc:LineExtensionAmount>
            <cac:TaxTotal>
                <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($item->pivot->quantity * ($item->pivot->price / (1 + $order->tax_value))) - ($item->pivot->discount / (1+$order->tax_value))) * $order->tax_value), 4, '.', '')}}</cbc:TaxAmount>
                <cbc:RoundingAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($item->pivot->quantity * ($item->pivot->price / (1 + $order->tax_value))) - ($item->pivot->discount / (1+$order->tax_value))) * $order->tax_value), 4, '.', '') +  number_format((($item->pivot->quantity * ($item->pivot->price / (1 + $order->tax_value))) - ($item->pivot->discount / (1+$order->tax_value))), 4, '.', '')  }}</cbc:RoundingAmount>
                <cac:TaxSubtotal>
                    <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($item->pivot->quantity * ($item->pivot->price / (1 + $order->tax_value))) - ($item->pivot->discount / (1+$order->tax_value))) * $order->tax_value), 4, '.', '')}}</cbc:TaxAmount>
                    <cac:TaxCategory>
                        <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5305">{{$order->tax_char}}</cbc:ID>
                        <cbc:Percent>{{$order->tax_value * 100}}</cbc:Percent>
                        <cac:TaxScheme>
                            <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>
                        </cac:TaxScheme>
                    </cac:TaxCategory>
                </cac:TaxSubtotal>
            </cac:TaxTotal>
            <cac:Item>
                <cbc:Name> {{$item->pivot->product_name}} </cbc:Name>
            </cac:Item>
            <cac:Price>
                <cbc:PriceAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($item->pivot->price / (1+$order->tax_value), 4, '.', '')}}</cbc:PriceAmount>
                <cac:AllowanceCharge>
                    <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
                    <cbc:AllowanceChargeReason>DISCOUNT</cbc:AllowanceChargeReason>
                    <cbc:Amount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($item->pivot->discount / (1 + $order->tax_value), 4, '.', '')}}</cbc:Amount>
                </cac:AllowanceCharge>
            </cac:Price>
        </cac:InvoiceLine>
    @endforeach
    @if($order->extra_items != null)
    @foreach($order->extra_items as $key2=>$extra)
        <cac:InvoiceLine>
            <cbc:ID>{{ count($order->products) + 1 + $key2 }}</cbc:ID>
            <cbc:InvoicedQuantity unitCode="PCE">{{ $extra->quantity }}</cbc:InvoicedQuantity>
            <cbc:LineExtensionAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format((($extra->quantity * ($extra->price / (1 + $order->tax_value))) - ($extra->discount / (1+$order->tax_value))), 4, '.', '')  }}</cbc:LineExtensionAmount>
            <cac:TaxTotal>
                <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($extra->quantity * ($extra->price / (1 + $order->tax_value))) - ($extra->discount / (1+$order->tax_value))) * $order->tax_value), 4, '.', '')}}</cbc:TaxAmount>
                <cbc:RoundingAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($extra->quantity * ($extra->price / (1 + $order->tax_value))) - ($extra->discount / (1+$order->tax_value))) * $order->tax_value), 4, '.', '') +  number_format((($extra->quantity * ($extra->price / (1 + $order->tax_value))) - ($extra->discount / (1+$order->tax_value))), 4, '.', '')}}</cbc:RoundingAmount>
                <cac:TaxSubtotal>
                    <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($extra->quantity * ($extra->price / (1 + $order->tax_value))) - ($extra->discount / (1+$order->tax_value))) * $order->tax_value), 4, '.', '')}}</cbc:TaxAmount>
                    <cac:TaxCategory>
                        <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5305">{{$order->tax_char}}</cbc:ID>
                        <cbc:Percent>{{$order->tax_value * 100}}</cbc:Percent>
                        <cac:TaxScheme>
                            <cbc:ID schemeAgencyID="6" schemeID="UN/ECE 5153">VAT</cbc:ID>
                        </cac:TaxScheme>
                    </cac:TaxCategory>
                </cac:TaxSubtotal>
            </cac:TaxTotal>
            <cac:Item>
                <cbc:Name> {{$extra->name}} </cbc:Name>
            </cac:Item>
            <cac:Price>
                <cbc:PriceAmount
                    currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($extra->price / (1+$order->tax_value), 4, '.', '')}}</cbc:PriceAmount>
                <cac:AllowanceCharge>
                    <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
                    <cbc:AllowanceChargeReason>DISCOUNT</cbc:AllowanceChargeReason>
                    <cbc:Amount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($extra->discount / (1 + $order->tax_value), 4, '.', '')}}</cbc:Amount>
                </cac:AllowanceCharge>
            </cac:Price>
        </cac:InvoiceLine>
    @endforeach
    @endif
</Invoice>
