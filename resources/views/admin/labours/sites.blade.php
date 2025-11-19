@foreach ($sites as $site)
    <tr data-id="{{ $site->id }}" data-name="{{ $site->site_name }}" data-address="{{ $site->site_address }}">
        <td>{{ $site->site_name }}</td>
        <td>{{ $site->site_address }}</td>
    </tr>
@endforeach
