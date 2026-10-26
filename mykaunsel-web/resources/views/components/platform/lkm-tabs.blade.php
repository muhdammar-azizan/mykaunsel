@props(['active'])

{{-- The two parts of the LKM Directory: the records, and counselors' requests to have their record checked again. --}}
<x-platform.status-tabs label="LKM Directory sections" :active="$active" :tabs="[
    ['key' => 'directory', 'label' => 'Directory', 'count' => \App\Models\LkmDirectorySnapshot::count(), 'url' => route('platform.lkm')],
    ['key' => 'reviews', 'label' => 'Review Requests', 'count' => \App\Models\LkmReviewRequest::pendingCount(), 'url' => route('platform.lkm.reviews')],
]" />
