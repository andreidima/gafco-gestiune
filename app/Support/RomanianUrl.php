<?php

namespace App\Support;

class RomanianUrl
{
    private const EXACT_PATHS = [
        '/login' => '/autentificare',
        '/dashboard' => '/acasa',
        '/driver-requests' => '/cereri-sofer',
        '/tasks/dispatch' => '/sarcini/situatie-soferi',
        '/settings/alerts' => '/setari/alerte',
        '/reception-intakes/create' => '/documente-de-procesat/trimite',
        '/transfers/source-options' => '/transferuri/optiuni-sursa',
        '/consumption-reports/allocation-proposal' => '/consumuri/propunere-alocare',
        '/consumption-reports/stock-options' => '/consumuri/optiuni-stoc',
        '/field/driver' => '/teren/sofer',
        '/field/site-manager' => '/teren/sef-santier',
        '/field/worker' => '/teren/muncitor',
        '/qr-scan' => '/scanare-qr',
    ];

    private const PREFIXES = [
        '/reception-documents' => '/documente-receptie',
        '/consumption-reports' => '/consumuri',
        '/supplier-receptions' => '/receptii',
        '/negotiated-orders' => '/comenzi-negociate',
        '/reception-intakes' => '/documente-de-procesat',
        '/tracked-assets' => '/echipamente',
        '/catalog-items' => '/nomenclator',
        '/driver-requests' => '/cereri-sofer',
        '/notifications' => '/notificari',
        '/transfers' => '/transferuri',
        '/inventory' => '/inventar',
        '/locations' => '/locatii',
        '/projects' => '/proiecte',
        '/suppliers' => '/furnizori',
        '/settings' => '/setari',
        '/returns' => '/retururi',
        '/reports' => '/rapoarte',
        '/alerts' => '/alerte',
        '/tasks' => '/sarcini',
        '/field' => '/teren',
        '/users' => '/utilizatori',
    ];

    public function translate(?string $url): ?string
    {
        if (! is_string($url) || $url === '' || str_starts_with($url, '//')) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        if (isset($parts['host']) && ! $this->isApplicationHost((string) $parts['host'])) {
            return $url;
        }

        $path = $parts['path'] ?? '';
        $translatedPath = $this->translatePath($path);
        if ($translatedPath === $path) {
            return $url;
        }

        $prefix = '';
        if (isset($parts['scheme'], $parts['host'])) {
            $prefix = $parts['scheme'].'://'.$parts['host'];
            if (isset($parts['port'])) {
                $prefix .= ':'.$parts['port'];
            }
        }

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $prefix.$translatedPath.$query.$fragment;
    }

    private function translatePath(string $path): string
    {
        if (isset(self::EXACT_PATHS[$path])) {
            return self::EXACT_PATHS[$path];
        }

        foreach (self::PREFIXES as $english => $romanian) {
            if ($path === $english || str_starts_with($path, $english.'/')) {
                $path = $romanian.substr($path, strlen($english));
                break;
            }
        }

        $path = preg_replace('#/create$#', '/adauga', $path) ?? $path;
        $path = preg_replace('#/edit$#', '/modifica', $path) ?? $path;
        $path = preg_replace('#/download$#', '/descarca', $path) ?? $path;
        $path = preg_replace('#/preview$#', '/previzualizare', $path) ?? $path;

        return $path;
    }

    private function isApplicationHost(string $host): bool
    {
        $applicationHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($applicationHost) && strcasecmp($host, $applicationHost) === 0;
    }
}
