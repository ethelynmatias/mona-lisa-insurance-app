<?php

namespace App\Enums;

enum NowCertsEntity: string
{
    case Insured  = 'Insured';
    case Policy   = 'Policy';
    case Driver   = 'Driver';
    case Vehicle  = 'Vehicle';
    case Property = 'Property';
    case Contact  = 'Contact';
    case InsuredLocation  = 'InsuredLocation';
    case GeneralLiabilityNotice = 'GeneralLiabilityNotice';
    case PolicyCoverage = 'PolicyCoverage';
}
