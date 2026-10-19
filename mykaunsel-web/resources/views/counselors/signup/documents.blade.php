@push('styles')
    <style>
        .masuk { animation: masuk .5s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        .uz { opacity: 0; transform: translateY(10px); animation: masuk .45s cubic-bezier(.32,.72,0,1) both; animation-delay: var(--tunda, 0s); }
        .uz-zone[data-state="empty"] .uz-uploading,
        .uz-zone[data-state="empty"] .uz-done,
        .uz-zone[data-state="empty"] .uz-error { display: none; }
        .uz-zone[data-state="done"] .uz-empty,
        .uz-zone[data-state="done"] .uz-error { display: none; }
        .uz-zone[data-state="error"] .uz-empty,
        .uz-zone[data-state="error"] .uz-done { display: none; }
        #d3Modal { transition: opacity .2s ease; }
        #d3ModalCard { transition: transform .25s cubic-bezier(.34,1.56,.64,1); }
        @media (prefers-reduced-motion: reduce) {
            .masuk, .uz { animation: none; opacity: 1; transform: none; }
        }
    </style>
@endpush

<x-auth-layout
    title="Upload Documents — MyKaunsel"
    image-slot="upload-visual"
    aside-title="Almost done."
    aside-subtitle="Upload these documents so our team can complete your review."
    content-max-width="460px"
>
    <div class="masuk">
        <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Upload your documents') }}</h1>
        <p class="mt-2 text-[13.5px] leading-relaxed text-navy/60">{{ __('Please upload clear, legible copies in PDF, JPG, or PNG format. Maximum 5MB per file.') }}</p>

        <div class="mt-5 flex items-start gap-2.5 rounded-[10px] p-3.5" style="background:rgba(14,42,51,.045)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0F6B7D" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            <p class="text-[12.5px] leading-relaxed text-navy/65">{{ __('All documents are reviewed by our team and kept confidential. They are only used to verify your professional registration.') }}</p>
        </div>

        <form id="d3Form" method="POST" action="{{ route('counselors.signup.documents.store') }}" enctype="multipart/form-data" class="mt-6 flex flex-col gap-4" novalidate>
            @csrf

            @php
                $docs = [
                    ['key' => 'cert', 'label' => 'Registered Counselor Certificate', 'labelMy' => 'Sijil Pendaftaran Kaunselor', 'hint' => 'Your official KB registration certificate from LKM.', 'existing' => $profile->cert_document_path],
                    ['key' => 'pa', 'label' => 'Practicing Certificate', 'labelMy' => 'Sijil Perakuan Amalan', 'hint' => 'Your current PA certificate showing validity dates.', 'existing' => $profile->pa_document_path],
                    ['key' => 'ic', 'label' => 'Identity Card (IC) or Passport', 'labelMy' => null, 'hint' => 'Used to confirm your identity matches your LKM registration.', 'existing' => $profile->ic_document_path],
                ];
            @endphp

            @foreach ($docs as $index => $doc)
                <div class="uz" data-doc="{{ $doc['key'] }}" style="--tunda:{{ $index * 0.08 }}s">
                    <p class="mb-1 text-[13.5px] font-medium text-navy/80">{{ __($doc['label']) }} @if ($doc['labelMy']) <span class="text-navy/40">({{ $doc['labelMy'] }})</span> @endif</p>
                    <p class="mb-2.5 text-[11.5px] text-navy/45">{{ __($doc['hint']) }}</p>
                    <div class="uz-zone relative rounded-[14px] border-2 border-dashed p-8 text-center" style="border-color:rgba(14,42,51,.2); background:rgba(14,42,51,.02)" data-state="{{ $doc['existing'] ? 'done' : 'empty' }}">
                        <input type="file" name="{{ $doc['key'] }}" class="uz-input absolute inset-0 h-full w-full cursor-pointer opacity-0" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="uz-empty">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto text-navy/40" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="m9 15 3-3 3 3"/></svg>
                            <p class="mt-3 text-[13.5px] text-navy/55">{{ __('Drag and drop your file here, or') }}</p>
                            <span class="mt-3 inline-flex items-center whitespace-nowrap rounded-full border border-navy/25 px-4 py-2 text-[13px] font-medium text-navy">{{ __('Browse files') }}</span>
                            <p class="mt-3 text-[11px] text-navy/40">PDF, JPG {{ __('or') }} PNG · Max 5MB</p>
                        </div>
                        <div class="uz-done text-left" style="position:relative;z-index:1">
                            <div class="flex items-center gap-3">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F6B7D" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                                <div class="min-w-0 flex-1">
                                    <p class="uz-filename truncate text-[13.5px] font-medium text-navy">{{ $doc['existing'] ? basename($doc['existing']) : '' }}</p>
                                    <p class="uz-filesize text-[11.5px] text-navy/45"></p>
                                </div>
                                <svg class="uz-check shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0F6B7D" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                                <button type="button" class="uz-remove grid h-6 w-6 shrink-0 place-items-center rounded-full text-navy/40 hover:bg-navy/5 hover:text-navy" aria-label="Remove file">×</button>
                            </div>
                            <p class="mt-2 text-[11.5px] text-teal">{{ $doc['existing'] ? __('Already uploaded — choose a new file to replace') : __('Ready to upload') }}</p>
                        </div>
                        <div class="uz-error text-left" style="position:relative;z-index:1">
                            <div class="flex items-center gap-2 text-[13px]" style="color:#C4574A">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                <span class="uz-error-msg"></span>
                            </div>
                        </div>
                    </div>
                    @error($doc['key'])<p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
                </div>
            @endforeach

            <button type="submit" id="d3SubmitBtn" class="btn-utama mt-2 grid h-[46px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Submit for Review') }}</button>
            <button type="button" id="d3SaveLater" class="text-center text-[13.5px] font-medium text-teal">{{ __('Save and finish later') }}</button>
        </form>
    </div>

    <!-- MODAL: Save your progress -->
    <div id="d3Modal" hidden class="fixed inset-0 z-50 grid place-items-center bg-navy/40 p-6" style="opacity:0">
        <div id="d3ModalCard" class="w-full max-w-[380px] rounded-2xl bg-cream p-6" style="transform:scale(.95)">
            <h2 class="text-[19px] font-semibold tracking-tightest">{{ __('Save your progress?') }}</h2>
            <p class="mt-2.5 text-[13.5px] leading-relaxed text-navy/60">{{ __('Your uploaded documents will be saved. You can come back to finish this step anytime from your dashboard.') }}</p>
            <div class="mt-6 flex gap-3">
                <button type="button" id="d3ModalContinue" class="flex-1 rounded-full border border-navy/25 px-5 py-2.5 text-[13.5px] font-medium text-navy transition-all duration-200 hover:-translate-y-0.5 hover:border-navy hover:bg-navy/5">{{ __('Continue uploading') }}</button>
                <button type="submit" form="d3Form" id="d3ModalSave" class="flex-1 rounded-full px-5 py-2.5 text-[13.5px] font-medium text-white transition-all duration-200 hover:-translate-y-0.5" style="background:#4A7DFF">{{ __('Save for later') }}</button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                function humanSize(bytes) {
                    if (bytes < 1024) return bytes + ' B';
                    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
                    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
                }

                [].slice.call(document.querySelectorAll('.uz-zone')).forEach(function (zone) {
                    var input = zone.querySelector('.uz-input');
                    var filenameEls = zone.querySelectorAll('.uz-filename');
                    var filesizeEl = zone.querySelector('.uz-filesize');
                    var errorMsg = zone.querySelector('.uz-error-msg');

                    function showFile(file) {
                        if (file.size > 5 * 1024 * 1024) {
                            zone.setAttribute('data-state', 'error');
                            errorMsg.textContent = 'File is too large. Maximum size is 5MB.';
                            input.value = '';
                            return;
                        }
                        zone.setAttribute('data-state', 'done');
                        filenameEls.forEach(function (el) { el.textContent = file.name; });
                        filesizeEl.textContent = humanSize(file.size);
                    }

                    input.addEventListener('change', function () {
                        if (input.files && input.files[0]) showFile(input.files[0]);
                    });

                    zone.addEventListener('dragover', function (e) { e.preventDefault(); });
                    zone.addEventListener('drop', function (e) {
                        e.preventDefault();
                        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                            var dt = new DataTransfer();
                            dt.items.add(e.dataTransfer.files[0]);
                            input.files = dt.files;
                            showFile(e.dataTransfer.files[0]);
                        }
                    });

                    var removeBtn = zone.querySelector('.uz-remove');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', function () {
                            input.value = '';
                            zone.setAttribute('data-state', 'empty');
                        });
                    }
                });

                var modal = document.getElementById('d3Modal');
                document.getElementById('d3SaveLater').addEventListener('click', function () {
                    modal.hidden = false;
                    requestAnimationFrame(function () {
                        modal.style.opacity = '1';
                        document.getElementById('d3ModalCard').style.transform = 'scale(1)';
                    });
                });
                document.getElementById('d3ModalContinue').addEventListener('click', function () {
                    modal.style.opacity = '0';
                    document.getElementById('d3ModalCard').style.transform = 'scale(.95)';
                    setTimeout(function () { modal.hidden = true; }, 200);
                });
            });
        </script>
    @endpush
</x-auth-layout>
