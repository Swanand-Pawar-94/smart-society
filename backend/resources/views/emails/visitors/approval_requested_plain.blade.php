Hello {{ $residentName }},

A visitor is requesting entry to your residence.

Visitor: {{ $visitorName }}
Type: {{ $visitorType }}
Flat: Flat {{ $flatNumber }} - Building {{ $building }}
Status: {{ $requestStatus }}
Time: {{ $requestedAt }}

@if(!empty($approveUrl))
To APPROVE, click:
{{ $approveUrl }}
@endif

@if(!empty($rejectUrl))
To REJECT, click:
{{ $rejectUrl }}
@endif

Or open the Smart Society mobile app -> Gate -> Visitor Request.

Regards,
Smart Society Security Team