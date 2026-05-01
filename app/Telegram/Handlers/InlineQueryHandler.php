<?php

namespace App\Telegram\Handlers;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Input\InputTextMessageContent;
use SergiX44\Nutgram\Telegram\Types\Inline\InlineQueryResultArticle;

class InlineQueryHandler
{
    public function __invoke(Nutgram $bot): void
    {
        $userbot = $bot->getMe()->username;
        $userId = $bot->userId();

        $results = [];

        $results[] = InlineQueryResultArticle::make(
            id: '1',
            title: "⭐ Do'stlarga ulashish",
            input_message_content: InputTextMessageContent::make(
                message_text: "<b>👋 Assalomu alaykum!</b> Sizga ishonchli va tezkor, eng muhumi arzon telegram yulduzlar kerakmi?\n\n<b>Unday bo'sa sizga o'zimizning ishonchli OctopusStars botimizni tavsiya etamiz. Ushbu bot Hamyon bob va Tezkor bo'lib mijozlar ko'nglidan joy olgan.</b>\n\n<b>📜 Asosiy savolga kelsak nega buncha arzon?</b>\n    - Ushbu botda hech qanday odam qatnashuvi bo'lmaganligi sababli va hamma ishni avtomatik robotlar qilganligi uchun bozordan ko'ra arzonroq.\n\n<b>🤖 Botga kirish manzili:</b> <a href='https://t.me/{$userbot}?start={$userId}'>@{$userbot}</a>",
                parse_mode: 'html'
            )
        );

        $bot->answerInlineQuery($results);
    }
}
