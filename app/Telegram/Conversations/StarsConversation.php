<?php

namespace App\Telegram\Conversations;

use DateTime;
use DateTimeZone;
use RuntimeException;
use App\Helpers\Payment;
use App\Helpers\Fragment;
use App\Models\Order;
use Illuminate\Support\Facades\Storage;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class StarsConversation extends Conversation
{
    protected int|string|null $stars = null;
    protected int|string|null $receiver = null;

    private function getStarPrice(): int
    {
        $price = trim(Storage::get('price.txt'));
        if (is_numeric($price)) {
            return (int)$price;
        }
        return 220;
    }

    private function calculatePrice(int $stars): int
    {
        return $stars * $this->getStarPrice();
    }

    private function formatPrice(int $price): string
    {
        return number_format($price, 0, '.', ' ') . " so'm";
    }

    public function start(Nutgram $bot)
    {
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    text: "⭐ 50 - " . $this->formatPrice($this->calculatePrice(50)),
                    callback_data: "stars_50"
                ),
                InlineKeyboardButton::make(
                    text: "⭐ 100 - " . $this->formatPrice($this->calculatePrice(100)),
                    callback_data: "stars_100"
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: "⭐ 150 - " . $this->formatPrice($this->calculatePrice(150)),
                    callback_data: "stars_150"
                ),
                InlineKeyboardButton::make(
                    text: "⭐ 200 - " . $this->formatPrice($this->calculatePrice(200)),
                    callback_data: "stars_200"
                )
            )
            ->addRow(
                InlineKeyboardButton::make(
                    text: "⭐ 500 - " . $this->formatPrice($this->calculatePrice(500)),
                    callback_data: "stars_500"
                ),
                InlineKeyboardButton::make(text: "◀️ Orqaga", callback_data: "home")
            );

        

        $bot->editMessageCaption(
            caption: "<b>🌟 Telegram Stars buyurtma</b>\n\n<b>✨ Siz qanchalik ko'p Stars olsangiz, shunchalik afzalliklarga ega bo'lasiz!</b>\n\n<blockquote>🔹 Minimal: <code>50</code>\n🔹 Maksimal: <code>10000</code></blockquote>\n\n<b>✅ Kerakli miqdorni tanlang yoki raqam bilan yuboring 👇</b>",
            parse_mode: "html",
            reply_markup: $keyboard
        );
        $this->next('askReceiver');
    }

    public function askReceiver(Nutgram $bot)
    {
        $update = false;

        if ($bot->callbackQuery()?->data) {
            $data = $bot->callbackQuery()->data;
            if (str_starts_with($data, 'stars_')) {
                $this->stars = (int)str_replace('stars_', '', $data);
                $update = true;
            }
        } elseif ($bot->message()?->text && is_numeric($bot->message()->text)) {
            $this->stars = (int)$bot->message()->text;
        } else {
            $bot->sendMessage("❌ Iltimos, son kiriting yoki tugmalardan foydalaning.");
            return;
        }

        if ($this->stars < 50 || $this->stars > 1000000) {
            $bot->sendMessage("⚠️ Iltimos, 50 dan 1000000 gacha bo'lgan son kiriting.");
            return;
        }


        $price = $this->getStarPrice();
        $cost = number_format($this->stars * $price, 2, '.', ' ');

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(text: "👤 O'zimga", callback_data: "self")
            )
            ->addRow(
                InlineKeyboardButton::make(text: "◀️ Orqaga", callback_data: "stars")
            );

        if ($update) {
            $bot->editMessageCaption(
                caption: "<b>⭐️ Stars sotib olish\n\n📊 Buyurtma ma'lumotlari:\n   └ 🎯 Miqdor:  {$this->stars} ⭐️\n   └ 💰 Narxi: {$cost} so'm</b>\n\n<b>👤 Kimga yuboramiz?\n📝 @username kiriting:</b>",
                parse_mode: "html",
                reply_markup: $keyboard
            );
        } else {
            $bot->sendMessage(
                text: "<b>⭐️ Stars sotib olish\n\n📊 Buyurtma ma'lumotlari:\n   └ 🎯 Miqdor:  {$this->stars} ⭐️\n   └ 💰 Narxi: {$cost} so'm</b>\n\n<b>👤 Kimga yuboramiz?\n📝 @username kiriting:</b>",
                parse_mode: "html",
                reply_markup: $keyboard
            );
        }

        $this->next("finish");
    }

    public function finish(Nutgram $bot)
    {
        global $card, $paymentApi;

        $update = false;
        if ($bot->callbackQuery()) {
            if ($bot->callbackQuery()->data == 'self') {
                $this->receiver = $bot->callbackQuery()->message->chat->username ?? null;
                $update = true;

                if (empty($this->receiver)) {
                    $bot->answerCallbackQuery("⚠️ Siz foydalanuvchi nomi kiritmagansiz!");
                    return;
                }
            }
        } elseif ($bot->message()) {
            if (isset($bot->message()->text)) {
                $this->receiver = str_ireplace('@', '', $bot->message()->text);
            }
        } else {
            $bot->sendMessage("Iltimos, qabul qiluvchini kiriting.");
            return $this->askReceiver($bot);
        }

        try {
            $authToken = Storage::get('token.txt');
            $response = Fragment::getUserInfo($this->receiver, $authToken);
            $receiverName = $response['name'] ?? '';
        } catch (RuntimeException $e) {
            $getMessage = $e->getMessage();
            if (strpos($getMessage, 'API request failed with HTTP code') === 0) {
                $code = str_replace('API request failed with HTTP code ', '', $getMessage);
                if ($code == '404') {
                    $bot->sendMessage("⚠️ Bunday foydalanuvchi topilmadi. Iltimos, to'g'ri foydalanuvchi nomini kiriting (masalan, @username).");
                    return $this->askReceiver($bot);
                } else {
                    $bot->sendMessage("⚠️ Tizimda xatolik yuz berdi!. Iltimos, keyinroq qayta urinib ko'ring.");
                    return;
                }
            }
        }

        $price = $this->getStarPrice();
        $cost = $this->stars * $price;

        $result = $paymentApi->createInvoice($cost, 'UZS', 'unknown', "Telegram Yulduz uchun to'lov {$this->stars} ta", $card);
        $success = $result['success'];
        $message = $result['message'];

        if ($success) {
            $amount = $result['data']['amount'] ?? null;
            $api_key = $result['data']['api_key'] ?? null;

            if (empty($amount) || empty($api_key)) {
                $bot->answerCallbackQuery("Tizimda xatolik! Adminga xabar bering.");
                return;
            }

            $formatted = number_format($cost, 2, ".", " ");

            $order = Order::insert([
                'customer' => $bot->message()->chat->id,
                'receiver' => $this->receiver,
                'type' => 'stars',
                'quantity' => $this->stars,
                'cost' => $amount,
                'pay_method' => 'card',
                'pay_key' => $api_key,
            ]);
            $orderId = $order->id;

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make(text: "✅ To'lov qildim", callback_data: "ipaid_{$orderId}")
                );

            if ($update) {
                $response = $bot->editMessageCaption(
                    caption: "<b>📊 To'lov ma'lumotlari:</b>\n\n<b>📋 Buyurtma:</b>\n<b>└ 🆔 Buyurtma raqami:</b> {$orderId}\n<b>└ 🎯 Yulduzlar:</b> {$this->stars} ta\n<b>└ 💰 Narxi:</b> {$formatted} so'm\n<b>└ 👤 Qabul qiluvchi:</b> {$receiverName} (@{$this->receiver})\n\n<b>💳 Karta ma'lumotlari:</b>\n<b>└ 🏦 Karta raqami:</b> <code>{$card}</code>\n<b>└ 💵 To'lov miqdori:</b> <code>{$amount}</code> so'm\n\n<b>⚠️ Muhim:</b> Ko'rsatilgan miqdorni to'lang: {$amount} so'm <b>1 so'm kam ham ko'p ham emas</b>",
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
            } else {
                $response = $bot->sendMessage(
                    text: "<b>📊 To'lov ma'lumotlari:</b>\n\n<b>📋 Buyurtma:</b>\n<b>└ 🆔 Buyurtma raqami:</b> {$orderId}\n<b>└ 🎯 Yulduzlar:</b> {$this->stars} ta\n<b>└ 💰 Narxi:</b> {$formatted} so'm\n<b>└ 👤 Qabul qiluvchi:</b> {$receiverName} (@{$this->receiver})\n\n<b>💳 Karta ma'lumotlari:</b>\n<b>└ 🏦 Karta raqami:</b> <code>{$card}</code>\n<b>└ 💵 To'lov miqdori:</b> <code>{$amount}</code> so'm\n\n<b>⚠️ Muhim:</b> Ko'rsatilgan miqdorni to'lang: {$amount} so'm <b>1 so'm kam ham ko'p ham emas</b>",
                    parse_mode: ParseMode::HTML,
                    reply_markup: $keyboard
                );
            }

            $msgId = $response->message_id;
            Order::where('id', $orderId)->update(['msg_id' => $msgId]);

            $this->next('paid');
        } else {
            $reply = $message == 'Try again' ? "Qayta urinib ko'ring!" : "Tizimda xatolik! Adminga xabar bering.";
            $bot->answerCallbackQuery($reply);
        }
    }

    public function paid(Nutgram $bot)
    {
        $orderId = str_ireplace('ipaid_', '', $bot->callbackQuery()->data ?? '');
        $order = Order::find($orderId);

        if (!$order) {
            $bot->deleteMessage($bot->callbackQuery()->message->chat->id, $bot->callbackQuery()->message->message_id);
            $bot->sendMessage(text: "⚠️ Buyurtma topilmadi!");
            $this->end();
            return;
        }

        if (in_array($order->status, ['completed', 'canceled', 'failed', 'in progress'])) {
            $bot->deleteMessage($bot->callbackQuery()->message->chat->id, $bot->callbackQuery()->message->message_id);
            $bot->sendMessage(text: "⚠️ Buyurtma yakunlangan yokida bekor qilingan bo'lishi mumkin.");
            $this->end();
            return;
        }

        $userId = $bot->callbackQuery()->message->chat->id;
        if ($order->customer != $userId) {
            $bot->deleteMessage($bot->callbackQuery()->message->chat->id, $bot->callbackQuery()->message->message_id);
            $bot->sendMessage(text: "⚠️ Buyurtma sizga tegishlik emas.");
            $this->end();
            return;
        }

        if (!in_array($order->type, ['premium', 'stars'])) {
            $bot->deleteMessage($bot->callbackQuery()->message->chat->id, $bot->callbackQuery()->message->message_id);
            $bot->sendMessage(text: "⚠️ Buyurtma eski bo'lishi mumkin.");
            $this->end();
            return;
        }

        $result = (new Payment)->checkInvoice($order['pay_key']);
        if (!$result['success']) {
            $bot->deleteMessage($bot->callbackQuery()->message->chat->id, $bot->callbackQuery()->message->message_id);
            $bot->sendMessage(text: "⚠️ Texnik xatolik! Administratorga to'lov vaqtida muammo deb xabar yuboring.");
            $this->end();
            return;
        }

        $status = $result['data']['status'];
        $createdAt = new DateTime($result['data']['created_at'], new DateTimeZone('UTC'));
        $timeLimit = (new DateTime('now', new DateTimeZone('UTC')))->modify('-10 minutes');

        if ($createdAt < $timeLimit) {
            $bot->deleteMessage($bot->callbackQuery()->message->chat->id, $bot->callbackQuery()->message->message_id);
            $bot->sendMessage(text: "⚠️ To'lov vaqti tugagan. Buyurtma uchun to'lov vaqti 10 minut etib belgilangan.");
            $this->end();
            return;
        }

        if ($status == 'pending') {
            $bot->answerCallbackQuery(text: "⏳ To'lov qilinishi kutilmoqda...");
            return;
        }

        if ($status == 'failed') {
            Order::where('id', $orderId)->update(['status' => 'failed']);
            $bot->deleteMessage($bot->callbackQuery()->message->chat->id, $bot->callbackQuery()->message->message_id);
            $bot->sendMessage(text: "⛔ To'lov vaqti tugagan!");
            $this->end();
            return;
        }

        if ($status == 'success') {
            Order::where('id', $orderId)->update(['status' => 'canceled']);
            $bot->deleteMessage($bot->callbackQuery()->message->chat->id, $bot->callbackQuery()->message->message_id);
            $bot->sendMessage(text: "✅ To'lov allaqachon qabul bo'lgan!");
            $this->end();
            return;
        }

        if ($status == 'approved') {
            $bot->deleteMessage($bot->callbackQuery()->message->chat->id, $bot->callbackQuery()->message->message_id);
            $bot->sendMessage(
                text: "<b>✅ To'lov qabul qilindi!</b> <blockquote>‼️ Iltimos, agar hisobingizga 2 daqiqa oralig'ida yulduzlar yetkazilmasa administratorga xabar bering.</blockquote>",
                parse_mode: ParseMode::HTML
            );

            Order::where('id', $orderId)->update(['status' => 'in progress']);

            $authToken = Storage::get('token.txt');
            $response = $order['type'] == 'stars' ? Fragment::buyStars($order['receiver'], $order['quantity'], $authToken) : Fragment::buyPremium($order['receiver'], $order['quantity'], $authToken);

            if (!empty($response['errors']) || !empty($response['error'])) {
                Order::where('id', $orderId)->update(['status' => 'failed']);
                $bot->sendMessage(text: "<b>⚠️ Yulduz yuborishda xatolik!</b>\n\n<i>Administatorga to'lov cheki va buyurtma ma'lumotlarini yuborsangiz yulduzlarni albatta yuboradi!</i>\n😊 <b>Noqulaylik uchun uzur so'raymiz :)</b>", parse_mode: ParseMode::HTML);
                $this->end();
                return;
            }

            if ($response["success"]) {
                Order::where('id', $orderId)->update(['status' => 'completed']);
                $bot->sendMessage("<b>✅ Xaridiniz uchun rahmat, barcha yulduzlar hisobingizga yetkazildi!</b>\n\n<i>Kelasi xaridlarni kutib qolaman. 😊</i>", parse_mode: ParseMode::HTML);
                $this->end();
            }
        }
    }
}
