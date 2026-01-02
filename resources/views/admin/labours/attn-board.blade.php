<div class="attn table" id="attnBoard">

    {{-- LEFT SIDE: LABOURS --}}
    <div class="left">
        <div class="days-head" id="attnDays">
            <div class="d">
            <div class="">Labours</div>
            <div class="">-</div>
            </div>
        </div>
        <div  id="attnLabours">

            @foreach ($attendance_labours as $labour)
                <div class="lab labour-tooltip" data-id="{{ $labour->id }}"
                    data-tooltip="
                Name: {{ $labour['name'] }}
                Father Name: {{ $labour['father_name'] }}
                CNIC: {{ $labour['cnic'] }}
                Phone: {{ $labour['phone'] }}
                Role: {{ $labour['role'] }}
                Daily Wage: Rs {{ $labour['daily_wage'] }}
                ">

                    <span class="nm">{{ $labour->name }} ({{ substr($labour['cnic'], -4) }})</span>
                    <span class="muted small">
                        {{ $labour->phone }} · {{ $labour->role }} · Rs&nbsp;{{ $labour->daily_wage }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    @php
        use Carbon\Carbon;
        [$start, $end, $weekDays] = loadAttendanceWeek($week ?? null);
    @endphp


    {{-- RIGHT SIDE --}}
    <div class="right">

        {{-- DAYS HEADER (F → T WEEK) --}}
        <div class="days-head">
            @foreach ($weekDays as $day)
                @php
                    $carbon = Carbon::parse($day);
                    $dayShort = strtoupper(substr($carbon->format('D'), 0, 1)); // F, S, S, M, T, W, T
                    $dateNum = $carbon->format('d');
                @endphp

                <div class="d {{ $day == now()->format('Y-m-d') ? 'today' : '' }}">
                    <div class="day-top">{{ $dayShort }}</div>
                    <div class="day-date">{{ $dateNum }}</div>
                </div>
            @endforeach
        </div>


        {{-- LABOUR ROWS --}}
        <div class="rows" id="attnRows">
            @foreach ($attendance_labours as $labour)
                <div class="row-days">

                    @foreach ($weekDays as $day)
                        @php
                            $attendance = $labour->attendances->where('date', $day)->first();

                            $status = 'present';
                            $icon = '-';
                            $iconClass = 'none';

                            if ($attendance) {
                                if ($attendance->hours == 4) {
                                    $status = 'leave';
                                    $icon = 'H';
                                    $iconClass = 'leave';
                                } elseif ($attendance->status == 'present') {
                                    $status = 'present';
                                    $icon = '✓';
                                    $iconClass = 'tick';
                                } else {
                                    $status = 'absent';
                                    $icon = '✗';
                                    $iconClass = 'cross';
                                }
                            }

                            $userData = [
                                'id' => $labour->id,
                                'name' => $labour->name,
                                'role' => $labour->role,
                                'date' => $day,
                                'status' => $status,
                                'hours' => $attendance->hours ?? 8,
                                'ot' => $attendance->ot_hours ?? 0,
                                'site' => $attendance->site_id ?? null,
                                'rate' => $labour->daily_wage,
                                'amount' => $attendance->amount ?? $labour->daily_wage,
                            ];
                            $isDisabled =  $attendance?->site_id &&$selectedSiteId && $attendance?->site_id != $selectedSiteId;
                        @endphp

                        <div class="cell {{ $isDisabled ? 'disabled' : '' }}" data-id="{{ $labour->id }}" data-date="{{ $day }}"
                            data-user='@json($userData)'>

                            <span data-tooltips="{{ $attendance?->site->site_name ?? '' }}" class="ico {{ $iconClass }}">{{ $icon }}</span>

                            @if ($attendance && $attendance->ot_hours > 0)
                                <span class="ico tick ot-badge">{{ number_format($attendance->ot_hours) }}</span>
                            @endif
                        </div>
                    @endforeach

                </div>
            @endforeach
        </div>

    </div>
</div>
