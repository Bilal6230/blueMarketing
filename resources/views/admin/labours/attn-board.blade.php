<div class="attn table" id="attnBoard">

    {{-- LEFT SIDE: LABOURS --}}
    <div class="left">
        <div class="days-head">
            <div class="d">
                <div class="day-top">Labours</div>
                <div>-</div>
                {{-- ✅ TOTAL LABOURS --}}
                <div class="day-total">
                    Total Labours:{{ $attendance_labours->count() }}
                </div>
            </div>
        </div>


        <div id="attnLabours" class="scroll-container">
            @foreach ($attendance_labours as $labour)
                <div class="lab labour-tooltip" data-id="{{ $labour->id }}"
                    data-tooltip="
                        Name: {{ $labour->name }}
                        Father Name: {{ $labour->father_name }}
                        CNIC: {{ $labour->cnic }}
                        Phone: {{ $labour->phone }}
                        Role: {{ $labour->role }}
                        Daily Wage: Rs {{ $labour->daily_wage }}
                    ">
                    <span class="nm">{{ $labour->name }} ({{ substr($labour->cnic, -4) }})</span>
                    <span class="muted small">
                        {{ $labour->phone }} · {{ $labour->role }} · Rs {{ $labour->daily_wage }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    @php
        use Carbon\Carbon;
        [$start, $end, $weekDays] = loadAttendanceWeek($week ?? null);
        $today = Carbon::today();
    @endphp

    {{-- RIGHT SIDE --}}
    <div class="right">

        {{-- DAYS HEADER --}}
        <div class="days-head">
            @foreach ($weekDays as $day)
                @php
                    $carbon = Carbon::parse($day);
                    $dayShort = strtoupper($carbon->format('D'));
                    $dateNum = $carbon->format('d');

                    // ✅ COUNT PRESENT FOR THIS DAY
                    $presentCount = 0;
                    foreach ($attendance_labours as $labour) {
                        $att = $labour->attendances->where('date', $day)->first();
                        if ($att && $att->status === 'present' && $att->hours >= 8) {
                            $presentCount++;
                        }
                    }
                @endphp

                <div class="d {{ $day == $today->format('Y-m-d') ? 'today' : '' }}">
                    <div class="day-top">{{ $dayShort }}</div>
                    <div class="day-date">{{ $dateNum }}</div>

                    {{-- ✅ TOTAL PRESENT --}}
                    <div class="day-total">Total Attendance: {{ $presentCount }}</div>
                </div>
            @endforeach
        </div>

        {{-- LABOUR ROWS --}}
        <div class="rows scroll-container" id="attnRows">
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
                                } elseif ($attendance->status === 'present') {
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
                            $cellDate = Carbon::parse($day);
                            $diffFromToday = $cellDate->diffInDays($today, false);
                            $isDateDisabled = $diffFromToday > 1;

                            $isSiteDisabled = $attendance?->site_id
                                && $selectedSiteId
                                && $attendance->site_id != $selectedSiteId;

                            $isDisabled = $isDateDisabled || $isSiteDisabled;
                        @endphp

                        <div class="cell {{ $isDisabled ? 'disabled' : '' }}"
                             data-id="{{ $labour->id }}"
                             data-date="{{ $day }}" data-user='@json($userData)'>

                            <span class="ico {{ $isSiteDisabled ? 'none' : $iconClass }}"
                                  style="{{ $isSiteDisabled ? 'background:#9db4dd;' : '' }}"
                                  data-tooltips="{{ $attendance?->site->site_name ?? '' }}">
                                {{ $isSiteDisabled ? '-' : $icon }}
                            </span>

                            @if ($attendance && $attendance->ot_hours > 0)
                                <span class="ico tick ot-badge">
                                    {{ number_format($attendance->ot_hours) }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

    </div>
</div>
