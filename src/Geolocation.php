<?php declare(strict_types=1);

namespace CrowdSecBouncer;

use Exception;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use InvalidArgumentException;
use BadMethodCallException;

/**
 * The Library geolocation helper. You'll find here methods used for geolocation purposes
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Geolocation
{

    protected array $maxmindCountry = [];

    private array $resultTemplate = ['country' => "", 'not_found' => "", 'error' => ""];

    /**
     * Retrieve a country from a MaxMind database
     * @param string $ip
     * @param string $databaseType
     * @param string $databasePath
     * @return array
     * @throws Exception
     */
    private function getMaxMindCountry(string $ip, string $databaseType, string $databasePath): array
    {
        if (!isset($this->maxmindCountry[$ip][$databaseType][$databasePath])) {
            $result = $this->resultTemplate;
            try {
                $reader = new Reader($databasePath);
                switch ($databaseType) {
                    case Constants::MAXMIND_COUNTRY:
                        $record = $reader->country($ip);
                        break;
                    case Constants::MAXMIND_CITY:
                        $record = $reader->city($ip);
                        break;
                    default:
                        throw new Exception("Unknown MaxMind database type:$databaseType");
                }
                $result['country'] = $record->country->isoCode;
            } catch (AddressNotFoundException $e) {
                $result['not_found'] = $e->getMessage();
            } catch (InvalidDatabaseException|InvalidArgumentException|BadMethodCallException $e) {
                $result['error'] = $e->getMessage();
            }

            $this->maxmindCountry[$ip][$databaseType][$databasePath] = $result;
        }

        return $this->maxmindCountry[$ip][$databaseType][$databasePath];
    }

    /**
     * Retrieve country from a geo-localised IP
     * @param array $geolocConfig
     * @param string $ip
     * @return array
     * @throws Exception
     */
    public function getCountryResult(array $geolocConfig, string $ip): array
    {
        $result = $this->resultTemplate;
        $saveInSession = !empty($geolocConfig['save_in_session']);
        if ($country = Session::getSessionVariable('crowdsec_geolocation_country')) {
            $result['country'] = $country;

            return $result;
        } elseif ($notFoundError = Session::getSessionVariable('crowdsec_geolocation_not_found')) {
            $result['not_found'] = $notFoundError;

            return $result;
        }
        $currentIp = empty($geolocConfig['test_public_ip']) ? $ip : $geolocConfig['test_public_ip'];
        switch ($geolocConfig['type']) {
            case Constants::GEOLOCATION_TYPE_MAXMIND:
                $configPath = $geolocConfig[Constants::GEOLOCATION_TYPE_MAXMIND];
                $result =
                    $this->getMaxMindCountry($currentIp, $configPath['database_type'], $configPath['database_path']);
                break;
            default:
                throw new Exception('Unknown Geolocation type:' . $geolocConfig['type']);
        }

        if($saveInSession){
            if (!empty($result['country'])) {
                Session::setSessionVariable('crowdsec_geolocation_country', $result['country']);
            } elseif ( !empty($result['not_found'])) {
                Session::setSessionVariable('crowdsec_geolocation_not_found', $result['not_found']);
            }
        }

        return $result;
    }

    public function clearGeolocationSessionContext()
    {
        Session::unsetSessionVariable('crowdsec_geolocation_country');
        Session::unsetSessionVariable('crowdsec_geolocation_not_found');
    }
}
