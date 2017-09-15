<?php

declare(strict_types=1);

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\Chain;

use Geocoder\Collection;
use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Provider\Provider;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
final class Chain implements Provider, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Provider[]
     */
    private $providers = [];

    private $name = '';

    /**
     * @param Provider[] $providers
     * @param Name $name
     */
    public function __construct(array $providers = [], $name = 'chain')
    {
        $this->providers = $providers;
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $quotaExceeded = false;
        foreach ($this->providers as $provider) {
            try {
                $quotaExceeded = false;
                $result = $provider->geocodeQuery($query);

                if (!$result->isEmpty()) {
                    return $result;
                }
                else
                {
                    break;
                }
            }
            catch (QuotaExceeded $e){
                $quotaExceeded = true;
            }
            catch (\Throwable $e) {
                break;
//                $this->log(
//                    'alert',
//                    sprintf('Provider "%s" could geocode address: "%s".', $provider->getName(), $query->getText()),
//                    ['exception' => $e]
//                );
            }
        }
        if($quotaExceeded){
            throw new QuotaExceeded();
        }
        return new AddressCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function reverseQuery(ReverseQuery $query): Collection
    {
        foreach ($this->providers as $provider) {
            try {
                $result = $provider->reverseQuery($query);

                if (!$result->isEmpty()) {
                    return $result;
                }
            } catch (\Throwable $e) {
                $coordinates = $query->getCoordinates();
                $this->log(
                    'alert',
                    sprintf('Provider "%s" could reverse coordinates: %f, %f.', $provider->getName(), $coordinates->getLatitude(), $coordinates->getLongitude()),
                    ['exception' => $e]
                );
            }
        }

        return new AddressCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
//        return 'chain';
        return $this->name;
    }

    /**
     * Adds a provider.
     *
     * @param Provider $provider
     *
     * @return Chain
     */
    public function add(Provider $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * @param $level
     * @param $message
     * @param array $context
     */
    private function log($level, $message, array $context = [])
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }
}
