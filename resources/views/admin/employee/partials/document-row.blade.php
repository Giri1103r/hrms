{{-- One Documents row on the employee Add / Edit form. $index is a number, or __INDEX__ inside the JS template. --}}
<div class="row document-row align-items-start">
    <div class="col-md-4 mb-3">
        <div class="form-group form-input">
            <label class="form-label require">Document Type</label>
            <select name="documents[{{ $index }}][document_type_id]" class="form-control document-type">
                <option value="">Select Document Type</option>
                @foreach ($documentTypeList as $type)
                    <option value="{{ $type->id }}" @selected((string) $selected === (string) $type->id)>{{ $type->document_name }}</option>
                @endforeach
            </select>
            @if (is_numeric($index))
                @error("documents.$index.document_type_id") <span class="invalid-feedback d-block">{{ $message }}</span> @enderror
            @endif
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="form-group form-input">
            <label class="form-label require">Files</label>
            <input type="file" name="documents[{{ $index }}][files][]" class="form-control document-files" multiple
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            @if (is_numeric($index))
                @php
                    $fileError = $errors->first("documents.$index.files") ?: $errors->first("documents.$index.files.*");
                @endphp
                @if ($fileError)
                    <span class="invalid-feedback d-block">{{ $fileError }}</span>
                @endif
            @endif
        </div>
    </div>
    <div class="col-md-2 mb-3 d-flex align-items-end" style="min-height: 70px;">
        <button type="button" class="btn btn-outline-danger remove-document-row" title="Remove this document">
            <i class="fa-solid fa-trash"></i> Remove
        </button>
    </div>
</div>
