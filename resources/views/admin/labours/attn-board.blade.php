<div class="attn table" id="attnBoard">
    <div class="left" id="attnLabours">
        <div class="days-head" id="attnDays">
            <div class="d"> Labours</div>
        </div>

        @foreach ($attendance_labours as $labour)
            <div class="lab" data-id="{{ $labour->id }}">
                <span class="nm">{{ $labour->name }}</span>
                <span class="muted small">
                    {{ $labour->phone }} · {{ $labour->role }} · Rs&nbsp;{{ $labour->daily_wage }}
                </span>
            </div>
        @endforeach
    </div>

    @php
        use Carbon\Carbon;
        $today = Carbon::now();
        [$start, $end, $weekDays] = loadAttendanceWeek($week ?? now()->format('o-\WW'));
    @endphp

    <div class="right">

        {{-- HEADER DAYS --}}
        <div class="days-head">
            @foreach ($weekDays as $day)
                @php
                    $isFriday = Carbon::parse($day)->isFriday();
                @endphp

                <div class="d
                    {{ $day == now()->format('Y-m-d') ? 'today' : '' }}
                    {{ $isFriday ? 'disabled-friday' : '' }}
                ">
                    {{ Carbon::parse($day)->format('d') }}
                </div>
            @endforeach
        </div>

        {{-- LABOUR ROWS --}}
        <div class="rows" id="attnRows">
            @foreach ($attendance_labours as $labour)
                <div class="row-days">

                    @foreach ($weekDays as $day)
                        @php
                            $isFriday = Carbon::parse($day)->isFriday();
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
                        @endphp

                        <div class="cell {{ $isFriday ? 'disabled-friday' : '' }}"
                             data-user='{{ json_encode($userData) }}'>
                            <span class="ico {{ $iconClass }}">{{ $icon }}</span>
                        </div>

                    @endforeach

                </div>
            @endforeach
        </div>

    </div>

</div>

