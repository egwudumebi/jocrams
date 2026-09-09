<script setup>
defineProps({
    status: { type: String, required: true },
});

const classes = {
    submitted: 'bg-amber-100 text-amber-800',
    payment_pending: 'bg-orange-100 text-orange-800',
    under_review: 'bg-blue-100 text-blue-800',
    revision_requested: 'bg-orange-100 text-orange-800',
    resubmitted: 'bg-violet-100 text-violet-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
};

const labels = {
    submitted: 'Submitted',
    payment_pending: 'Payment pending',
    under_review: 'Under Review',
    revision_requested: 'Revision Requested',
    resubmitted: 'Resubmitted',
    approved: 'Approved',
    rejected: 'Rejected',
};
</script>

<template>
    <span :class="['badge capitalize', classes[status] || 'bg-slate-100 text-slate-700']">
        {{ labels[status] || status.replace(/_/g, ' ') }}
    </span>
</template>
