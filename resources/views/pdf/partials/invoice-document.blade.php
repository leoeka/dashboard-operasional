{{--
    Tampilan invoice bergaya contoh Exito Bali.
    Dipakai oleh PDF (pdf/invoice.blade.php) dan badan email.
    Hanya <table> dan inline style, karena dompdf dan klien email tidak mendukung flex/grid.

    Variabel:
      $doc     array dari InvoicePdfService::data()
      $logoSrc src logo (public_path() untuk PDF, $message->embed() untuk email)
--}}
@php
    $orange = '#F58220';
    $blue   = '#1BA5D8';
    $muted  = '#555555';
    $line   = '#DDDDDD';
    $font   = "'DejaVu Sans', Arial, Helvetica, sans-serif";
@endphp
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; font-family:{!! $font !!}; font-size:12px; line-height:1.5; color:#222222;">
    <tr>
        <td valign="middle" style="padding:0 0 16px 0;">
            <img src="{{ $logoSrc }}" alt="Exito Bali" height="48" style="height:48px; display:block; border:0;">
        </td>
        <td valign="middle" align="right" style="padding:0 0 16px 0;">
            <div style="font-size:26px; line-height:1.2; color:{{ $orange }};">INVOICE</div>
            <div style="font-size:11px; color:{{ $muted }};">{{ $doc['payment_method'] }}</div>
        </td>
    </tr>

    <tr>
        <td valign="top" style="padding:10px 0; border-top:1px solid {{ $line }}; border-bottom:1px solid {{ $line }};">
            <strong>Date:</strong> {{ $doc['date'] }}
            @if (!empty($doc['due_date']))
                <br><strong>Due Date:</strong> {{ $doc['due_date'] }}
            @endif
        </td>
        <td valign="top" align="right" style="padding:10px 0; border-top:1px solid {{ $line }}; border-bottom:1px solid {{ $line }};">
            <strong>Invoice No:</strong> {{ $doc['number'] }}
        </td>
    </tr>

    <tr>
        <td valign="top" width="50%" style="padding:16px 0 20px 0;">
            <strong>Invoiced To:</strong><br>
            <strong>{{ $doc['client']['name'] }}</strong><br>
            @if (!empty($doc['client']['address']))
                {!! nl2br(e($doc['client']['address'])) !!}<br>
            @endif
            @if (!empty($doc['client']['phone']))
                Phone : {{ $doc['client']['phone'] }}<br>
            @endif
            @if (!empty($doc['client']['email']))
                Email : {{ $doc['client']['email'] }}
            @endif
        </td>
        <td valign="top" width="50%" align="right" style="padding:16px 0 20px 0;">
            <strong>Pay To:</strong><br>
            <strong>{{ $doc['company']['name'] }}</strong><br>
            @if (!empty($doc['company']['bank']))
                {{ $doc['company']['bank'] }}<br>
            @endif
            @if (!empty($doc['company']['account']))
                {{ $doc['company']['account'] }}<br>
            @endif
            @if (!empty($doc['company']['holder']))
                a/n {{ $doc['company']['holder'] }}
            @endif
        </td>
    </tr>

    <tr>
        <td colspan="2" style="padding:0;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse; border:1px solid {{ $line }};">
                <tr>
                    <td style="background:{{ $blue }}; color:#FFFFFF; font-weight:bold; padding:10px 12px;" width="20%">Service</td>
                    <td style="background:{{ $blue }}; color:#FFFFFF; font-weight:bold; padding:10px 12px;">Description</td>
                    <td style="background:{{ $blue }}; color:#FFFFFF; font-weight:bold; padding:10px 12px;" width="15%" align="right">Rate</td>
                    <td style="background:{{ $blue }}; color:#FFFFFF; font-weight:bold; padding:10px 12px;" width="7%" align="right">QTY</td>
                    <td style="background:{{ $blue }}; color:#FFFFFF; font-weight:bold; padding:10px 12px;" width="17%" align="right">Amount</td>
                </tr>
                @foreach ($doc['items'] as $item)
                    <tr>
                        <td valign="top" style="padding:12px; border-bottom:1px solid {{ $line }};">{{ $item['service'] }}</td>
                        <td valign="top" style="padding:12px; border-bottom:1px solid {{ $line }};">{!! nl2br(e($item['description'])) !!}</td>
                        <td valign="top" align="right" style="padding:12px; border-bottom:1px solid {{ $line }};">{{ $item['rate'] }}</td>
                        <td valign="top" align="right" style="padding:12px; border-bottom:1px solid {{ $line }};">{{ $item['qty'] }}</td>
                        <td valign="top" align="right" style="padding:12px; border-bottom:1px solid {{ $line }};">{{ $item['amount'] }}</td>
                    </tr>
                @endforeach
                @foreach (['subtotal' => 'Subtotal', 'discount' => 'Discount', 'tax' => 'Tax'] as $key => $label)
                    @if (!empty($doc[$key]))
                        <tr>
                            <td colspan="4" align="right" style="padding:8px 12px; border-bottom:1px solid {{ $line }};">{{ $label }}:</td>
                            <td align="right" style="padding:8px 12px; border-bottom:1px solid {{ $line }};">{{ $doc[$key] }}</td>
                        </tr>
                    @endif
                @endforeach
                <tr>
                    <td colspan="4" align="right" style="padding:12px; background:#F5F5F5; font-weight:bold;">Total:</td>
                    <td align="right" style="padding:12px; background:#F5F5F5; font-weight:bold;">{{ $doc['total'] }}</td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td colspan="2" style="padding:24px 0 0 0;">
            <strong style="color:{{ $orange }};">NOTE :</strong>
            @if (!empty($doc['note']))
                <div style="padding-top:6px; color:{{ $muted }};">{!! nl2br(e($doc['note'])) !!}</div>
            @endif
        </td>
    </tr>
</table>