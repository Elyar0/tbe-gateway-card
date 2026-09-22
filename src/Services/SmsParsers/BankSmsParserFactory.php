<?php

namespace TelegramBotEssentials\GatewayCard\Services\SmsParsers;

class BankSmsParserFactory
{
    /**
     * @var array<string, class-string<BankSmsParser>>
     */
    private const PARSERS = [
        'blu_bank' => BluBankParser::class,
    ];

    public static function make(?string $bank): ?BankSmsParser
    {
        if ($bank === null || ! isset(self::PARSERS[$bank])) {
            return null;
        }

        return app(self::PARSERS[$bank]);
    }

    /**
     * SELECT-setting options: [key => label]. Adding a bank is just adding
     * an entry here and to PARSERS above.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'blu_bank' => 'Blu Bank',
        ];
    }
}
