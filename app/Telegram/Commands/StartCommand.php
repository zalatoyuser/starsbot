<?php

namespace App\Telegram\Commands;

use App\Models\TelegramUser;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Handlers\Type\Command;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class StartCommand extends Command
{
    protected string $command = 'start';

    protected ?string $description = 'Ishga tushurish';

    public function handle(Nutgram $bot): void
    {
        $updateType = !empty($bot->callbackQuery()->data) ? true : false;

        $userbot = $bot->getMe();
        $userbot = $userbot->username;
        $firstName = $bot->user()?->first_name;

        $user = TelegramUser::firstOrCreate(
            ['telegram_id' => $bot->userId()],
        );

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make("⭐ Stars", callback_data: "stars"),
                InlineKeyboardButton::make("↗️ Ulashish", switch_inline_query: "")
            );



        $replyText = "<b>👋 Salom, {$firstName}!</b>\n<blockquote>🛍️ Botimiz orqali osonligina telegram yulduzlarni arzonga xarid qilishingiz mumkin.</blockquote>\n<b>Quyidagi menyudan keraklisini tanlang↩️</b>";
        if ($updateType) {
            $bot->deleteMessage($bot->chatId(), $bot->messageId());
        }

        $bot->sendPhoto(
            photo: InputFile::make(public_path('banner.jpg')),
            caption: $replyText,
            parse_mode: "html",
            reply_markup: $keyboard
        );
    }
}
