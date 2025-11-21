@foreach ($sites as $site)
    <tr data-id="{{ $site->id }}" data-name="{{ $site->site_name }}" data-address="{{ $site->site_address }}">
        <td>{{ $site->site_name }}</td>
        <td>{{ $site->site_address }}</td>
        <td><button class="btn editSiteBtn" data-id="{{ $site->id }}"
                siteData="{{ json_encode($site) }}">Edit</button>
        </td>
    </tr>
@endforeach
