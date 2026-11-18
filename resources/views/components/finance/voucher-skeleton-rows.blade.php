{{-- Server-rendered first-frame finance skeleton; replaced by the shared jQuery loader after bootstrap. --}}
@for ($skeletonRow = 0; $skeletonRow < ($rows ?? 10); $skeletonRow++)
    <tr class="bm-skeleton-row bm-skeleton-neutral" aria-hidden="true">
        @for ($skeletonColumn = 0; $skeletonColumn < ($columns ?? 8); $skeletonColumn++)
            <td><span class="bm-skeleton-bar" style="width:{{ 85 - (($skeletonRow + $skeletonColumn) % 4) * 12 }}%"></span></td>
        @endfor
    </tr>
@endfor
