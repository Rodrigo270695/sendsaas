<?php

use App\Support\Geo\MojibakeFixer;

function geoName(string $entity): string
{
    return html_entity_decode($entity, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function asMojibake(string $utf8): string
{
    return mb_convert_encoding($utf8, 'UTF-8', 'Windows-1252');
}

it('repara mojibake típico de ubigeo peruano', function (): void {
    $brena = 'BRE'.geoName('&Ntilde;').'A';
    $peru = 'Per'.geoName('&uacute;');
    $jesus = 'JES'.geoName('&Uacute;').'S MAR'.geoName('&Iacute;').'A';

    expect(MojibakeFixer::repair(asMojibake($brena)))->toBe($brena);
    expect(MojibakeFixer::repair(asMojibake($peru)))->toBe($peru);
    expect(MojibakeFixer::repair(asMojibake($jesus)))->toBe($jesus);
});

it('no altera nombres ya correctos', function (): void {
    $brena = 'BRE'.geoName('&Ntilde;').'A';
    $jesus = 'JES'.geoName('&Uacute;').'S MAR'.geoName('&Iacute;').'A';

    expect(MojibakeFixer::repair('LIMA'))->toBe('LIMA');
    expect(MojibakeFixer::repair('AREQUIPA'))->toBe('AREQUIPA');
    expect(MojibakeFixer::repair($jesus))->toBe($jesus);
    expect(MojibakeFixer::repair($brena))->toBe($brena);
});
