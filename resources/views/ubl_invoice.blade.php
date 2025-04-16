<? xml version = "1.0" encoding = "UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">

    <cbc:ProfileID>reporting:1.0</cbc:ProfileID>
    <cbc:ID>{{ $order->tax_number }}</cbc:ID>
    <cbc:UUID>{{ $order->uuid }}</cbc:UUID>
    <cbc:IssueDate>{{ $order->taxed_at }}</cbc:IssueDate>
    <cbc:InvoiceTypeCode name="{{($order->options->taxed && $order->options->taxed) ? 022 : 012 }}">388
    </cbc:InvoiceTypeCode>
    <cbc:Note>{{$order->invoice_notes}}</cbc:Note>
    <cbc:DocumentCurrencyCode>{{ config('jo_fotara.currency') }}</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>{{ config('jo_fotara.currency') }}</cbc:TaxCurrencyCode>
    <cac:AdditionalDocumentReference>
        <cbc:ID>ICV</cbc:ID>
        <cbc:UUID> {{ $order->id }}</cbc:UUID>
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
                <cbc:CompanyID> {{ config('jo_fotara.company_id') }} </cbc:CompanyID>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>{{ config('jo_fotara.seller_name') }} </cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingSupplierParty>


    <!-- Buyer Information -->
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID schemeID="{{$order->identity_number_type}}">{{$order->customer_identity_number}}</cbc:ID>
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
        <cbc:Amount currencyID="JO">{{$order->discount}}</cbc:Amount>
    </cac:AllowanceCharge>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="JO">{{$order->totalTax}}</cbc:TaxAmount>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:TaxExclusiveAmount currencyID="JO">{{$order->totalBeforDiscount}}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="JO">{{$order->totalAfterDiscountAndTax}}</cbc:TaxInclusiveAmount>
        <cbc:AllowanceTotalAmount currencyID="JO">{{$order->discount}}</cbc:AllowanceTotalAmount>
        <cbc:PayableAmount currencyID="JO">{{$order->totalAfterDiscountAndTax}}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>


    <!-- Line Items -->
    @foreach($order->products as $item)
        <cac:InvoiceLine>
            <cbc:ID>{{ $item->id }}</cbc:ID>
            <cbc:InvoicedQuantity unitCode="PCE">{{ $item->pivot->quantity }}</cbc:InvoicedQuantity>
            <cbc:LineExtensionAmount currencyID="JOD">{{ $item->pivot->quantity * ($item->pivot->price - ($item->pivot->price * $order->tax_value))  }}</cbc:LineExtensionAmount>
            <cac:TaxTotal>
                <cbc:TaxAmount currencyID="JO">{{$item->pivot->price * $item->pivot->quantity * $order->tax_value}}</cbc:TaxAmount>
                <cbc:RoundingAmount currencyID="JO">{{ $item->pivot->quantity * $item->pivot->price  }}</cbc:RoundingAmount>
                <cac:TaxSubtotal>
                    <cbc:TaxAmount currencyID="JO">{{$item->pivot->price * $item->pivot->quantity * $order->tax_value}}</cbc:TaxAmount>
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
                <cbc:PriceAmount currencyID="JO">{{$item->pivot->price - ($item->pivot->price * $order->tax_value)}}</cbc:PriceAmount>
                <cac:AllowanceCharge>
                    <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
                    <cbc:AllowanceChargeReason>DISCOUNT</cbc:AllowanceChargeReason>
                    <cbc:Amount currencyID="JO">0.00</cbc:Amount>
                </cac:AllowanceCharge>
            </cac:Price>
        </cac:InvoiceLine>
@endforeach
</Invoice>
