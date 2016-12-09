<?php
/**
 *
 */

namespace VK\Processors;

class Zodiak {
  /**
   * Zodiak types
   */
  const TYPE_NORMAL = 'normal';
  const TYPE_PHANATIC = 'phanatic';
  const TYPE_PHANATIC2 = 'phanatic2';
  const TYPE_NASA2016 = 'nasa2016';

  /**
   * All zodiak names
   */
  const Z_ARIES = 'Aries';
  const Z_TAURUS = 'Taurus';
  const Z_GEMINI = 'Gemini';
  const Z_CANCER = 'Cancer';
  const Z_LEO = 'Leo';
  const Z_VIRGO = 'Virgo';
  const Z_LIBRA = 'Libra';
  const Z_SCORPIO = 'Scorpio';
  const Z_OPHIUCHUS = 'Ophiuchus';
  const Z_SAGITTARIUS = 'Sagittarius';
  const Z_CAPRICORN = 'Capricorn';
  const Z_AQUARIUS = 'Aquarius';
  const Z_PISCES = 'Pisces';

  public static function getSignInfo($type = self::TYPE_NORMAL) {
    $data = [
      static::TYPE_NORMAL => [
        static::Z_ARIES => [
          'start' => '03.21',
          'end' => '04.19',
        ],
        static::Z_TAURUS => [
          'start' => '04.20',
          'end' => '05.20',
        ],
        static::Z_GEMINI => [
          'start' => '05.21',
          'end' => '06.20',
        ],
        static::Z_CANCER => [
          'start' => '06.21',
          'end' => '07.22',
        ],
        static::Z_LEO => [
          'start' => '07.23',
          'end' => '08.22',
        ],
        static::Z_VIRGO => [
          'start' => '08.23',
          'end' => '09.22',
        ],
        static::Z_LIBRA => [
          'start' => '09.23',
          'end' => '10.22',
        ],
        static::Z_SCORPIO => [
          'start' => '10.23',
          'end' => '11.21',
        ],
        static::Z_SAGITTARIUS => [
          'start' => '11.22',
          'end' => '12.21',
        ],
        static::Z_CAPRICORN => [
          'start' => '12.22',
          'end' => '01.19',
        ],
        static::Z_AQUARIUS => [
          'start' => '01.20',
          'end' => '02.18',
        ],
        static::Z_PISCES => [
          'start' => '02.19',
          'end' => '03.20',
        ],
      ],
    ];

    if (!isset($data[$type])) {
      throw new \Exception('Undefined type ' . $type);
    }

    return $data[$type];
  }

  public static function getSignSortedMap($type = self::TYPE_NORMAL) {
    static $cache = NULL;

    if (!isset($cache[$type])) {
      $map = static::getSignInfo($type);

      $cache[$type] = [];
      // Link to $cache
      $sorted = &$cache[$type];
      foreach ($map as $sign => $info) {
        $sorted[ $info['start'] ] = $sign;
        $sorted[ $info['end'] ] = $sign;
      }

      ksort($sorted);
    }

    return $cache[$type];
  }

  /**
   * @param string $date
   *  Format: DD.MM
   */
  public static function defineZodiakByDate(string $date, $type = self::TYPE_NORMAL) {
    list($date, $month) = explode('.', $date);

    $compare_string = sprintf('%02d.%02d', $month, $date);

    $sorted = static::getSignSortedMap($type);

    $sign = NULL;
    foreach ($sorted as $_dates => $_sign) {
      if (strcmp($_dates, $compare_string) >= 0) {
        $sign = $_sign;
        break;
      }
    }

    return $sign;
  }

  public static function _testZodiaks() {
    $map = static::getSignInfo(static::TYPE_NORMAL);

    $tz_utc = new \DateTimeZone('UTC');
    $tests = [
      'errors' => 0,
      'passes' => 0,
      'results' => [],
    ];

    foreach ($map as $sign => $info) {
      $start_date = \DateTime::createFromFormat('Y.m.d', '1970.' . $info['start'], $tz_utc);
      $end_date = \DateTime::createFromFormat('Y.m.d', '1970.' . $info['end'], $tz_utc);

      $start_date->setTime(0, 0, 0);
      $end_date->setTime(0, 0, 0);

      $one_day_interval = new \DateInterval('P1D');
      /**
       * @var $date \DateTime
       */
      for ($date = clone $start_date; $date->getTimestamp() <= $end_date->getTimestamp(); $date->add($one_day_interval)) {
        $test = (static::defineZodiakByDate($date->format('d.m'), static::TYPE_NORMAL) == $sign) ? true : false;
        $tests['results'][$date->format('m.d')] = [
          'sigh' => $sign,
          'test' => $test ? 'pass' : 'fail',
        ];

        $tests['errors'] += ($test ? 0 : 1);
        $tests['passes'] += ($test ? 1 : 0);
      }
    }

    return $tests;
  }
}
