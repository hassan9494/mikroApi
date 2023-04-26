@component('mail::message')
# Order 00001

Your order has been shipped!

@component('mail::table')
| Product         |   Price   |  Quantity  |  Total |
|:----------------|----------:|:----------:|-------:|
| col 1 is        |  $12 | $1600 |$1600 |
| col 2asdasds  asd asdad asd      |    $12   |   $12 |$12 |
| col 3 is        | $12 |    $1 |$1 |
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
