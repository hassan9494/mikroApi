@extends('layouts.mail.mail')
@section('styles')
    <style>
        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }
        table {
            font-family: arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }

        th {
            font-weight: bold;
        }

        td, th {
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
        }
    </style>
@endsection
@section('title')
    title
@endsection

@section('content')
    <div class="thanks-div" style="background-color: #fe5e00">
        <h1 class="text-center">
            Thank You for Your Order
        </h1>
    </div>
    <div class="welcome-div" style="">

        <h3>Hi {{$order->customer->name}}</h3>
        <h4>Just to let you know _ we've received your order #{{$order->id}},and its now being proccessed : </h4>
        <h4>Pay Cache Upon Delivery</h4>
        <h3>
            [Order #{{$order->id}}]<br>
            {{$order->created_at->format('F d, Y')}}
        </h3>
        <table class="products-table" style="margin-bottom: 15px">
            <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Price</th>
            </tr>
            </thead>
            <tbody>
            @foreach($order->products as $product)
                <tr>
                    <td>{{$product->name}}</td>
                    <td>{{$product->pivot->quantity}}</td>
                    <td>{{$product->pivot->price}} د.أ</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <table class="products-table" style="margin-bottom: 15px">

            <tbody>
            <tr>
                <th>Subtotal</th>
                <td>{{$order->subtotal}} د.أ</td>
            </tr>
            <tr>
                <th>ٍShipping</th>
                <td>Free shipping in Amman within 48 hours above 20 JD Orders</td>
            </tr>
            <tr>
                <th>Payment Methode</th>
                <td>Cash on delivery</td>
            </tr>
            <tr>
                <th>Total</th>
                <td>{{$order->total}} د.أ</td>
            </tr>
            </tbody>
        </table>
        <table class="products-table" style="margin-bottom: 15px">
            <thead>
            <tr>
                <th>Billing Address</th>
                <th>Shipping Address</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    @if(isset($order->shipping->address))
                        {{$order->shipping->address}}
                    @else
                        @if(isset($order->user))
                            @if(isset($order->user->primaryAddress))
                            {{$order->user->primaryAddress?->content}}
                            @else
                                ------------
                            @endif
                        @else
                            ------------
                        @endif
                    @endif
                </td>
                <td>
                    @if(isset($order->shipping->address))
                        {{$order->shipping->address}}
                    @else
                        ------------
                    @endif
                </td>
            </tr>
            </tbody>
        </table>

        <h4>
            Thank You for your order ,we will contact you for delivery within 24 - 48 hours
        </h4>
        <h4>
            شكرا لقد تم استلام طلبك , سنتواصل معكم للتوصيل خلال 24 - 48 ساعة
        </h4>
    </div>
@endsection
