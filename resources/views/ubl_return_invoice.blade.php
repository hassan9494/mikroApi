<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">


<cbc:ProfileID>reporting:1.0</cbc:ProfileID>
    <cbc:ID>{{ $order->number }}</cbc:ID>
    <cbc:UUID>{{ $order->uuid }}</cbc:UUID>
    <cbc:IssueDate>{{ \Carbon\Carbon::parse($order->taxed_at)->format('Y-m-d') }}</cbc:IssueDate>
    <cbc:InvoiceTypeCode name="{{$order->order->options->dept ? '022' : '012' }}">381</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>{{ config('jo_fotara.currency') }}</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>{{ config('jo_fotara.currency') }}</cbc:TaxCurrencyCode>
    <cac:BillingReference>
        <cac:InvoiceDocumentReference>
            <cbc:ID>{{$order->order->tax_number}}</cbc:ID>
            <cbc:UUID>{{ $order->order->uuid }}</cbc:UUID>
            <cbc:DocumentDescription>{{$order->old_final_total}}</cbc:DocumentDescription>
        </cac:InvoiceDocumentReference>
    </cac:BillingReference>
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

    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PostalAddress>
                <cac:Country>
                    <cbc:IdentificationCode>JO</cbc:IdentificationCode>
                </cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cac:TaxScheme>
                    <cbc:ID>VAT</cbc:ID>
                </cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>



    <!-- seller Income Source -->
    <cac:SellerSupplierParty>
        <cac:Party>
            <cac:PartyIdentification>
                <cbc:ID>{{ config('jo_fotara.seller_income_source') }}</cbc:ID>
            </cac:PartyIdentification>
        </cac:Party>
    </cac:SellerSupplierParty>

    <!-- Reason for return -->
    <cac:PaymentMeans>
        <cbc:PaymentMeansCode listID="UN/ECE 4461">10</cbc:PaymentMeansCode>
        <cbc:InstructionNote>{{$order->notes}}</cbc:InstructionNote>
    </cac:PaymentMeans>

    <!-- order discount -->
    <cac:AllowanceCharge>
        <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
        <cbc:AllowanceChargeReason>discount</cbc:AllowanceChargeReason>
        <cbc:Amount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{$order->final_discount}}</cbc:Amount>
    </cac:AllowanceCharge>

    <!-- order tax -->
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{$order->final_tax}}</cbc:TaxAmount>
    <cac:TaxSubtotal>
        <cbc:TaxableAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{$order->final_total}}</cbc:TaxableAmount>
        <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{$order->final_tax}}</cbc:TaxAmount>
        <cac:TaxCategory>
            <cbc:ID schemeID="UN/ECE 5305" schemeAgencyID="6">S</cbc:ID>
            <cbc:Percent>{{$order->tax_value * 100}}</cbc:Percent>
            <cac:TaxScheme>
                <cbc:ID schemeID="UN/ECE 5153" schemeAgencyID="6">VAT</cbc:ID>
            </cac:TaxScheme>
        </cac:TaxCategory>
    </cac:TaxSubtotal>
    </cac:TaxTotal>
    <!-- return order total -->
    <cac:LegalMonetaryTotal>
        <cbc:TaxExclusiveAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{$order->final_total}}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{$order->final_total - $order->final_discount + $order->final_tax}}</cbc:TaxInclusiveAmount>
        <cbc:AllowanceTotalAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{$order->final_discount}}</cbc:AllowanceTotalAmount>
        <cbc:PrepaidAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">0</cbc:PrepaidAmount>
        <cbc:PayableAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{$order->final_total - $order->final_discount +$order->final_tax}}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>




    <!-- Line Items -->
    @foreach($order->products as $key=>$item)
        @if($item->pivot->returned_quantity > 0)
        <cac:InvoiceLine>
            <cbc:ID>{{ $key + 1 }}</cbc:ID>
            <cbc:InvoicedQuantity unitCode="PCE">{{ $item->pivot->returned_quantity }}</cbc:InvoicedQuantity>
            <cbc:LineExtensionAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format((($item->pivot->returned_quantity * number_format(($item->pivot->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($item->pivot->discount / (1+$order->tax_value)), 9, '.', '')), 9, '.', '')  }}</cbc:LineExtensionAmount><cac:TaxTotal>
                <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($item->pivot->returned_quantity * number_format(($item->pivot->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($item->pivot->discount / (1+$order->tax_value)), 9, '.', '')) * $order->tax_value), 9, '.', '')}}</cbc:TaxAmount>
                <cbc:RoundingAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($item->pivot->returned_quantity * number_format(($item->pivot->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($item->pivot->discount / (1+$order->tax_value)), 9, '.', '')) * $order->tax_value) +  (($item->pivot->returned_quantity * number_format(($item->pivot->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($item->pivot->discount / (1+$order->tax_value)), 9, '.', '')), 9, '.', '')  }}</cbc:RoundingAmount>
                <cac:TaxSubtotal>
                    <cbc:TaxableAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format((($item->pivot->returned_quantity * number_format(($item->pivot->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($item->pivot->discount / (1+$order->tax_value)), 9, '.', '')), 9, '.', '')  }}</cbc:TaxableAmount>
                    <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($item->pivot->returned_quantity * number_format(($item->pivot->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($item->pivot->discount / (1+$order->tax_value)), 9, '.', '')) * $order->tax_value), 9, '.', '')}}</cbc:TaxAmount>
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
                <cbc:Name> {{$item->pivot->name}} </cbc:Name>
            </cac:Item>
            <cac:Price>
                <cbc:PriceAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($item->pivot->price / (1+$order->tax_value), 9, '.', '')}}</cbc:PriceAmount>
                <cac:AllowanceCharge>
                    <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
                    <cbc:AllowanceChargeReason>DISCOUNT</cbc:AllowanceChargeReason>
                    <cbc:Amount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($item->pivot->discount / (1 + $order->tax_value), 9, '.', '')}}</cbc:Amount>
                </cac:AllowanceCharge>
            </cac:Price>
        </cac:InvoiceLine>
        @endif
    @endforeach
    @if($order->extra_items != null)
    @foreach($order->extra_items as $key2=>$extra)
            @if($extra->returned_quantity > 0)
                <cac:InvoiceLine>
                    <cbc:ID>{{ count($order->products) + 1 + $key2 }}</cbc:ID>
                    <cbc:InvoicedQuantity unitCode="PCE">{{ $extra->returned_quantity }}</cbc:InvoicedQuantity>
                    <cbc:LineExtensionAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format((($extra->returned_quantity * number_format(($extra->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($extra->discount / (1+$order->tax_value)), 9, '.', '')), 9, '.', '')  }}</cbc:LineExtensionAmount><cac:TaxTotal>
                        <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($extra->returned_quantity * number_format(($extra->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($extra->discount / (1+$order->tax_value)), 9, '.', '')) * $order->tax_value), 9, '.', '')}}</cbc:TaxAmount>
                        <cbc:RoundingAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($extra->returned_quantity * number_format(($extra->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($extra->discount / (1+$order->tax_value)), 9, '.', '')) * $order->tax_value) +  (($extra->returned_quantity * number_format(($extra->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($extra->discount / (1+$order->tax_value)), 9, '.', '')), 9, '.', '')  }}</cbc:RoundingAmount>
                        <cac:TaxSubtotal>
                            <cbc:TaxableAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format((($extra->returned_quantity * number_format(($extra->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($extra->discount / (1+$order->tax_value)), 9, '.', '')), 9, '.', '')  }}</cbc:TaxableAmount>
                            <cbc:TaxAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{ number_format(((($extra->returned_quantity * number_format(($extra->price / (1 + $order->tax_value)), 9, '.', '')) - number_format(($extra->discount / (1+$order->tax_value)), 9, '.', '')) * $order->tax_value), 9, '.', '')}}</cbc:TaxAmount>
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
                        <cbc:PriceAmount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($extra->price / (1+$order->tax_value), 9, '.', '')}}</cbc:PriceAmount>
                        <cac:AllowanceCharge>
                            <cbc:ChargeIndicator>false</cbc:ChargeIndicator>
                            <cbc:AllowanceChargeReason>DISCOUNT</cbc:AllowanceChargeReason>
                            <cbc:Amount currencyID="{{ config('jo_fotara.currency_attribute') }}">{{number_format($extra->discount / (1 + $order->tax_value), 9, '.', '')}}</cbc:Amount>
                        </cac:AllowanceCharge>
                    </cac:Price>
                </cac:InvoiceLine>
            @endif
    @endforeach
    @endif
</Invoice>
