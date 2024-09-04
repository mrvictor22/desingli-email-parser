<?php

namespace App\Services;

use AutoMapperPlus\AutoMapper;
use AutoMapperPlus\Configuration\AutoMapperConfig;
use App\Models\SesEvent;
use App\DTO\TransformedEvent;

class SesMapperService
{

    protected $mapper;

    public function __construct()
    {
        $config = new AutoMapperConfig();
        $config->registerMapping(SesEvent::class, TransformedEvent::class)
            ->forMember('spam', function (SesEvent $source) {
                return $source->receipt['spamVerdict']['status'] === 'PASS';
            })
            ->forMember('virus', function (SesEvent $source) {
                return $source->receipt['virusVerdict']['status'] === 'PASS';
            })
            ->forMember('dns', function (SesEvent $source) {
                $spf = $source->receipt['spfVerdict']['status'] === 'PASS';
                $dkim = $source->receipt['dkimVerdict']['status'] === 'PASS';
                $dmarc = $source->receipt['dmarcVerdict']['status'] === 'PASS';
                return $spf && $dkim && $dmarc;
            })
            ->forMember('mes', function (SesEvent $source) {
                return date('F', strtotime($source->mail['timestamp']));
            })
            ->forMember('retrasado', function (SesEvent $source) {
                return $source->receipt['processingTimeMillis'] > 1000;
            })
            ->forMember('emisor', function (SesEvent $source) {
                return explode('@', $source->mail['source'])[0];
            })
            ->forMember('receptor', function (SesEvent $source) {
                return array_map(function ($recipient) {
                    return explode('@', $recipient)[0];
                }, $source->mail['destination']);
            });

        $this->mapper = new AutoMapper($config);
    }

    public function map(SesEvent $sesEvent): TransformedEvent
    {
        return $this->mapper->map($sesEvent, TransformedEvent::class);
    }
}
