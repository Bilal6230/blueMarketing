<div class="{{ $wrapperClass ?? 'header-container' }}">
    <img class="{{ $logoClass ?? 'logo' }}" src="{{ asset($branding['logo']) }}" alt="{{ $branding['title'] }}">
    <div class="{{ $centerClass ?? 'center' }}" style="{{ $centerStyle ?? '' }}">
        <h2 class="{{ $titleClass ?? 'title' }}">{{ $branding['title'] }}</h2>
        <div class="{{ $addressClass ?? 'address' }}">{{ $branding['address'] }}</div>
        {!! $extraHtml ?? '' !!}
    </div>
    <div class="{{ $rightClass ?? '' }}" style="{{ $rightStyle ?? '' }}">
        @foreach ($branding['phones'] as $phone)
            <div>{{ $phone }}</div>
        @endforeach
    </div>
</div>
