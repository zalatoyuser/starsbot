<?php
/** @var SergiX44\Nutgram\Nutgram $bot */

use Nutgram\Laravel\Facades\Telegram;
use SergiX44\Nutgram\Nutgram;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Here is where you can register telegram handlers for Nutgram. These
| handlers are loaded by the NutgramServiceProvider. Enjoy!
|
*/

Telegram::registerCommand(\App\Telegram\Commands\StartCommand::class);
Telegram::onCallbackQueryData('home', [\App\Telegram\Commands\StartCommand::class, 'handle']);
Telegram::onCallbackQueryData('stars', \App\Telegram\Conversations\StarsConversation::class);
Telegram::onInlineQuery(\App\Telegram\Handlers\InlineQueryHandler::class);