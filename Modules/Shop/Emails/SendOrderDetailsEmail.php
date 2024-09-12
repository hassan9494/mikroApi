<?php

namespace Modules\Shop\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderDetailsEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $details;
    public $order;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details,$order)
    {
        $this->details = $details;
        $this->order = $order;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        $subject = $this->details['subject'];
        $order = $this->order;
        return $this->subject($subject)
            ->view('mail.order.order_details',compact('order'));
    }
}
