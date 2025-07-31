<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderReadyForPickup extends Notification implements ShouldQueue
{
    use Queueable;

    protected $order;
    public $afterCommit = true;

    /**
     * Create a new notification instance.
     *
     * @param Order $order
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'order_id' => $this->order->order_id,
            'order_number' => $this->order->order_number,
            'seller_name' => $this->order->seller->stall_name ?? 'Seller #' . $this->order->seller_id,
            'message' => 'Your order is ready for pickup! Please collect it.',
            'type' => 'ready_for_pickup',
            'pickup_by' => now()->addSeconds(900)->toDateTimeString(),
        ];
    }
    
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
} 
