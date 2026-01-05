@foreach ($sites as $site)
    <tr data-id="{{ $site->id }}" data-name="{{ $site->site_name }}" data-address="{{ $site->site_address }}">
        <td>{{ $site->site_name }}</td>
        <td>{{ $site->site_address }}</td>
        <td><button class="icon-btn editSiteBtn"
                title="Edit Site" data-id="{{ $site->id }}"
                siteData="{{ json_encode($site) }}"><i class="fa fa-edit"></i></button>
        </td>
    </tr>
@endforeach
