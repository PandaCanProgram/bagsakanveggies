@php
    $formState = [
        'name' => old('name', $product->name ?? ''),
        'note' => old('note', $product->note ?? ''),
        'variants' => array_values(old('variants', $product->variants ?? [])),
        'errors' => collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'variants.'))->all(),
        'max' => \App\Http\Requests\Admin\SaveProductRequest::MAX_VARIANTS,
        'imageUrl' => $product->imageUrl(),
        'removeImage' => (bool) old('remove_image'),
        'maxImageBytes' => \App\Http\Requests\Admin\SaveProductRequest::MAX_IMAGE_KILOBYTES * 1024,
    ];
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="a-editor" x-data="productForm(@js($formState))" @input="dirty = true" @submit="submitting = true" novalidate>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="a-editor-main">
        @if ($errors->any())
            <div class="a-error-summary" role="alert" tabindex="-1" aria-labelledby="error-summary-title" x-init="$el.focus()">
                <h2 id="error-summary-title" class="a-error-summary-title">
                    <x-admin.icon name="alert-circle" :size="20" />
                    Please fix {{ trans_choice('this|these', count($errors->all())) }} before saving
                </h2>
                <ul>
                    @foreach ($errors->getMessages() as $key => $messages)
                        <li><a href="#{{ str_replace('.', '-', $key) }}">{{ $messages[0] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="a-card a-card-pad" aria-labelledby="details-title">
            <h2 id="details-title" class="a-section-title">Details</h2>

            <div class="a-stack">
                <div class="a-field">
                    <label for="name" class="a-label">Name <span class="a-required" aria-hidden="true">*</span></label>
                    <input id="name" name="name" type="text" class="a-input @error('name') is-invalid @enderror" x-model="name"
                           maxlength="100" required autocomplete="off" placeholder="e.g. Fresh Carrots"
                           aria-describedby="name-hint @error('name') name-error @enderror" @error('name') aria-invalid="true" @enderror>
                    <p id="name-hint" class="a-hint">Shown as the title on the store.</p>
                    @error('name')
                        <p id="name-error" class="a-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="a-field">
                    <label for="note" class="a-label">Note <span class="a-optional">(optional)</span></label>
                    <input id="note" name="note" type="text" class="a-input @error('note') is-invalid @enderror" x-model="note"
                           maxlength="100" autocomplete="off" placeholder="e.g. (Benguet)"
                           aria-describedby="note-hint @error('note') note-error @enderror" @error('note') aria-invalid="true" @enderror>
                    <p id="note-hint" class="a-hint">A short line under the name, like where it’s from.</p>
                    @error('note')
                        <p id="note-error" class="a-field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="a-card a-card-pad" aria-labelledby="photo-title">
            <h2 id="photo-title" class="a-section-title">Photo <span class="a-optional">(optional)</span></h2>
            <input type="hidden" name="remove_image" :value="removeImage ? 1 : 0" value="0">

            <div class="a-photo">
                <div class="a-photo-frame" x-show="previewImage" x-cloak>
                    <img :src="previewImage" alt="Photo of this veggie" class="a-photo-img">
                    <span class="a-photo-badge" x-show="newImageUrl">New photo · saved when you save</span>
                </div>

                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="a-file-input" x-ref="imageInput"
                       :tabindex="previewImage ? -1 : 0" @change="pickImage($event.target.files[0])"
                       aria-describedby="image-hint @error('image') image-error @enderror" @error('image') aria-invalid="true" @enderror>
                <label for="image" class="a-dropzone @error('image') is-invalid @enderror" x-show="!previewImage" :class="{ 'is-dragging': dragging }"
                       @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="dragging = false; pickImage($event.dataTransfer.files[0])">
                    <span class="a-dropzone-icon"><x-admin.icon name="image" :size="24" /></span>
                    <span class="a-dropzone-title">Choose a photo</span>
                    <span class="a-hint">or drag one here · JPG, PNG or WebP</span>
                </label>

                <div class="a-photo-actions" x-show="previewImage" x-cloak>
                    <button type="button" class="a-btn a-btn-secondary" @click="$refs.imageInput.click()" :disabled="processing">
                        <x-admin.icon name="image" :size="18" />
                        Replace photo
                    </button>
                    <button type="button" class="a-btn a-btn-ghost a-btn-danger-text" @click="removePhoto()" :disabled="processing">
                        <x-admin.icon name="trash" :size="18" />
                        Remove photo
                    </button>
                </div>

                <p class="a-photo-note" x-show="removeImage && !newImageUrl" x-cloak>
                    The current photo will be removed when you save.
                    <button type="button" class="a-link-btn" @click="undoRemove()">Undo</button>
                </p>
                <p class="a-hint" x-show="processing" x-cloak role="status">Preparing photo…</p>
                <p class="a-field-error" x-show="imageError" x-text="imageError" x-cloak role="alert"></p>
                @error('image')
                    <p id="image-error" class="a-field-error">{{ $message }}</p>
                @enderror
                <p id="image-hint" class="a-hint">Shown at the top of this veggie’s card on the store. Landscape photos look best; big phone photos are shrunk automatically.</p>
            </div>
        </section>

        <fieldset id="variants" class="a-card a-card-pad a-fieldset" tabindex="-1">
            <legend class="a-section-title">Sizes &amp; prices</legend>
            <p class="a-hint a-fieldset-hint">Customers pick from these on the store. Use whole pesos.</p>

            @error('variants')
                <p class="a-field-error">{{ $message }}</p>
            @enderror

            <div class="a-variant-head" aria-hidden="true">
                <span>Size</span>
                <span>Price</span>
                <span></span>
            </div>

            <ol class="a-variant-list">
                <template x-for="(variant, index) in variants" :key="variant.id">
                    <li class="a-variant-row">
                        <div class="a-field">
                            <label :for="`variants-${index}-label`" class="a-label a-label-sm a-variant-label">Size <span class="sr-only" x-text="index + 1"></span></label>
                            <input :id="`variants-${index}-label`" :name="`variants[${index}][label]`" type="text" class="a-input"
                                   x-model="variant.label" maxlength="40" required placeholder="e.g. per kg"
                                   :class="{ 'is-invalid': error(index, 'label') }"
                                   :aria-invalid="error(index, 'label') ? 'true' : null"
                                   :aria-describedby="error(index, 'label') ? `variants-${index}-label-error` : null">
                            <p class="a-field-error" :id="`variants-${index}-label-error`" x-show="error(index, 'label')" x-text="error(index, 'label')"></p>
                        </div>

                        <div class="a-field">
                            <label :for="`variants-${index}-price`" class="a-label a-label-sm a-variant-label">Price <span class="sr-only" x-text="`for size ${index + 1}, in pesos`"></span></label>
                            <div class="a-money">
                                <span class="a-money-sign" aria-hidden="true">₱</span>
                                <input :id="`variants-${index}-price`" :name="`variants[${index}][price]`" type="number" inputmode="numeric"
                                       min="1" step="1" class="a-input a-money-input" x-model="variant.price" required placeholder="0"
                                       :class="{ 'is-invalid': error(index, 'price') }"
                                       :aria-invalid="error(index, 'price') ? 'true' : null"
                                       :aria-describedby="error(index, 'price') ? `variants-${index}-price-error` : null">
                            </div>
                            <p class="a-field-error" :id="`variants-${index}-price-error`" x-show="error(index, 'price')" x-text="error(index, 'price')"></p>
                        </div>

                        <button type="button" class="a-icon-btn a-icon-btn-danger" @click="removeVariant(index)"
                                :disabled="variants.length === 1" :aria-label="`Remove size ${variant.label || index + 1}`"
                                :title="variants.length === 1 ? 'A veggie needs at least one size' : 'Remove this size'">
                            <x-admin.icon name="trash" :size="18" />
                        </button>
                    </li>
                </template>
            </ol>

            <button type="button" class="a-btn a-btn-secondary" @click="addVariant()" x-show="variants.length < max">
                <x-admin.icon name="plus" :size="18" />
                Add size
            </button>
            <p class="a-hint" x-show="variants.length >= max" x-cloak>You’ve reached the limit of <span x-text="max"></span> sizes.</p>
        </fieldset>

        <div class="a-form-actions">
            <a href="{{ route('admin.products.index') }}" class="a-btn a-btn-ghost">Cancel</a>
            <button type="submit" class="a-btn a-btn-primary" :disabled="submitting">
                <span x-text="submitting ? 'Saving…' : @js($submitLabel)">{{ $submitLabel }}</span>
            </button>
        </div>
    </div>

    <aside class="a-editor-side" aria-labelledby="preview-title">
        <div class="a-preview">
            <h2 id="preview-title" class="a-preview-title">
                <x-admin.icon name="eye" :size="16" />
                Store preview
            </h2>
            <div class="a-preview-card" aria-hidden="true">
                <img class="a-preview-img" :src="previewImage" alt="" x-show="previewImage" x-cloak>
                <div class="a-preview-body">
                    <div>
                        <div class="a-preview-name" x-text="name.trim() || 'Veggie name'"></div>
                        <div class="a-preview-note" x-show="note.trim()" x-text="note"></div>
                    </div>
                    <template x-for="variant in variants" :key="variant.id">
                        <div class="a-preview-variant">
                            <span x-text="variant.label.trim() || 'Size'"></span>
                            <strong x-text="formatPrice(variant.price)"></strong>
                        </div>
                    </template>
                </div>
            </div>
            <p class="a-hint">This is roughly how the card looks to customers.</p>
        </div>
    </aside>
</form>
