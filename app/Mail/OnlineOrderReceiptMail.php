<?php

namespace App\Mail;

use App\Models\PedidoOnline;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OnlineOrderReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PedidoOnline $order, public bool $includeReceipt = true) {}

    public function build(): self
    {
        $this->order->loadMissing(['venta.tienda', 'venta.cliente', 'venta.vendedor', 'venta.detalles.producto', 'venta.pagos']);
        $sale = $this->order->venta;
        $mail = $this->subject('Pago confirmado · Pedido '.$this->order->numero)
            ->view('emails.online-order-receipt')
            ->with(['order' => $this->order, 'sale' => $sale, 'includeReceipt' => $this->includeReceipt]);

        if (! $this->includeReceipt) {
            return $mail;
        }

        $receipt = view('ventas.receipt', ['venta' => $sale, 'format' => 'a4'])->render();
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', $sale->numero_venta).'.html';

        return $mail->attachData($receipt, $filename, ['mime' => 'text/html']);
    }
}
