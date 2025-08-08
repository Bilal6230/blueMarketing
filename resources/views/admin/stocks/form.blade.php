<div class="row">
    {{-- Stock Name --}}
    <div class="col-md-12 mb-3">
        <label for="{{ $idPrefix }}_stock_name" class="form-label fw-bold">Stock Name</label>
        <input type="text" name="stock_name" id="{{ $idPrefix }}_stock_name"
            class="form-control @error('stock_name') is-invalid @enderror"
            value="{{ old('stock_name', $stock->stock_name ?? '') }}" placeholder="Enter stock name" required>
        @error('stock_name')
            <span class="invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    {{-- Type --}}
    <div class="col-md-6 mb-3">
        <label for="{{ $idPrefix }}_type" class="form-label fw-bold">Type</label>
        <select name="type" id="{{ $idPrefix }}_type" class="form-control" required>
            <option value="">Select Type</option>
            <option value="purchase">Purchase</option>
            <option value="sale">Sale</option>
        </select>
    </div>

    {{-- Quantity --}}
    <div class="col-md-6 mb-3">
        <label for="{{ $idPrefix }}_quantity" class="form-label fw-bold">Quantity</label>
        <input type="number" name="quantity" id="{{ $idPrefix }}_quantity"
            class="form-control @error('quantity') is-invalid @enderror"
            value="{{ old('quantity', $stock->quantity ?? '') }}" placeholder="Enter quantity" required>
        @error('quantity')
            <span class="invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    {{-- Total Used --}}
    <div class="col-md-6 mb-3">
        <label for="{{ $idPrefix }}_total_used" class="form-label fw-bold">Total Used</label>
        <input type="number" name="total_used" id="{{ $idPrefix }}_total_used"
            class="form-control @error('total_used') is-invalid @enderror"
            value="{{ old('total_used', $stock->total_used ?? '') }}" placeholder="Enter total used" required>
        @error('total_used')
            <span class="invalid-feedback">{{ $message }}</span>
        @enderror
    </div>

    {{-- Total Remaining (calculated, readonly) --}}
    <div class="col-md-6 mb-3">
        <label for="{{ $idPrefix }}_total_remaining" class="form-label fw-bold">Total Remaining</label>
        <input type="number" name="total_remaining" id="{{ $idPrefix }}_total_remaining" class="form-control"
            value="{{ old('total_remaining', $stock->total_remaining ?? '') }}" readonly>
    </div>
</div>
