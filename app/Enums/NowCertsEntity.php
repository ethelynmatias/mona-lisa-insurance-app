<?php

namespace App\Enums;

enum NowCertsEntity: string
{
    case Insured  = 'Insured';
    case Policy   = 'Policy';
    case Driver   = 'Driver';
    case Vehicle  = 'Vehicle';
    case Property         = 'Property';
    case InsuredLocation  = 'InsuredLocation';
}
