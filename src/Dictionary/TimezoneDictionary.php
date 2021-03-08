<?php namespace App\Dictionary;

use Symfony\Component\Translation\TranslatorInterface;
use DateTimeZone;
use DateTime;

class TimezoneDictionary
{
    public static $timezones = [
        ['id' => '1', 'value' => 'US/Samoa'],
        ['id' => '2', 'value' => 'Pacific/Rarotonga'],
        ['id' => '3', 'value' => 'Pacific/Marquesas'],
        ['id' => '4', 'value' => 'Pacific/Gambier'],
        ['id' => '5', 'value' => 'America/Nome'],
        ['id' => '6', 'value' => 'America/Vancouver'],
        ['id' => '7', 'value' => 'America/Rio_Branco'],
        ['id' => '8','value' => 'America/St_Barthelemy'],
        ['id' => '9', 'value' => 'Atlantic/Stanley'],
        ['id' => '10', 'value' => 'Atlantic/South_Georgia'],
        ['id' => '11', 'value' => 'Atlantic/Cape_Verde'],
        ['id' => '12', 'value' => 'Atlantic/Reykjavik'],
        ['id' => '13', 'value' => 'Africa/Lagos'],
        ['id' => '14', 'value' => 'Europe/Malta'],
        ['id' => '15', 'value' => 'Europe/Minsk'],
        ['id' => '16', 'value' => 'Europe/Astrakhan'],
        ['id' => '17', 'value' => 'Asia/Kabul'],
        ['id' => '18', 'value' => 'Asia/Samarkand'],
        ['id' => '19', 'value' => 'Asia/Calcutta'],
        ['id' => '20', 'value' => 'Asia/Kathmandu'],
        ['id' => '21', 'value' => 'Asia/Bishkek'],
        ['id' => '22', 'value' => 'Asia/Hovd'],
        ['id' => '23', 'value' => 'Australia/Perth'],
        ['id' => '24', 'value' => 'Asia/Chita'],
        ['id' => '25', 'value' => 'Australia/Adelaide'],
        ['id' => '26', 'value' => 'Asia/Vladivostok'],
        ['id' => '27', 'value' => 'Asia/Sakhalin'],
        ['id' => '28', 'value' => 'Asia/Kamchatka'],
    ];

    public static function compileJsTimezonesView(TranslatorInterface $translator): array
    {
        $view = [];
        $timezones = self::$timezones;
        foreach ($timezones as $timezone) {
            // @TODO cache
            $targetTimeZone = new DateTimeZone($timezone['value']);
            $dateTime = new DateTime('now', $targetTimeZone);
            $timezoneOffset = $dateTime->format('P');
            $translatedTimezoneDesc = $translator->trans(
                "timezones.{$timezone['id']}",
                ['%offset%' => $timezoneOffset],
                'site'
            );
            $view[] = [
                'text' => $translatedTimezoneDesc,
                'value' => $timezone['value'],
            ];
        }

        return $view;
    }

    public static function getTimezonesValues(): array
    {
        $values = [];
        $timezones = self::$timezones;
        foreach ($timezones as $timezone) {
            $values[] = $timezone['value'];
        }

        return $values;
    }
}
