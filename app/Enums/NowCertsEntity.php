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
    case InsuredPolicies = 'InsuredPolicies';
    // case ServiceRequestsAddDriver = 'ServiceRequestsAddDriver';
    // case ServiceRequestsAddVehicle = 'ServiceRequestsAddVehicle';
    case VehicleCoverage = 'VehicleCoverage';
    case Claim = 'Claim';
    case PropertyLossClaim = 'PropertyLossClaim';
    case WorkerCompensationClaim = 'WorkerCompensationClaim';
}
