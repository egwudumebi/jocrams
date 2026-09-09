<script setup>
import { onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Write content…' },
    minHeight: { type: String, default: '8rem' },
});

const emit = defineEmits(['update:modelValue']);

const editor = ref(null);

function sync() {
    if (!editor.value) {
        return;
    }

    emit('update:modelValue', editor.value.innerHTML);
}

function exec(command, value = null) {
    editor.value?.focus();
    document.execCommand(command, false, value);
    sync();
}

function insertLink() {
    const url = window.prompt('Link URL');

    if (url) {
        exec('createLink', url);
    }
}

function onPaste(event) {
    event.preventDefault();
    const html = event.clipboardData?.getData('text/html') ?? '';
    const text = event.clipboardData?.getData('text/plain') ?? '';

    if (html && /<[a-z][\s\S]*>/i.test(html)) {
        document.execCommand('insertHTML', false, html);
    } else {
        document.execCommand('insertText', false, text);
    }

    sync();
}

onMounted(() => {
    if (editor.value && props.modelValue) {
        editor.value.innerHTML = props.modelValue;
    }
});

watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && editor.value.innerHTML !== value) {
            editor.value.innerHTML = value || '';
        }
    },
);
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap gap-1 border-b border-slate-100 bg-slate-50 px-2 py-1.5">
            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-white hover:text-slate-900" title="Bold" @click="exec('bold')"><strong>B</strong></button>
            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-white hover:text-slate-900" title="Italic" @click="exec('italic')"><em>I</em></button>
            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-white hover:text-slate-900" title="Underline" @click="exec('underline')"><span class="underline">U</span></button>
            <span class="mx-1 w-px self-stretch bg-slate-200" />
            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-white hover:text-slate-900" title="Heading" @click="exec('formatBlock', 'h3')">H</button>
            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-white hover:text-slate-900" title="Bullet list" @click="exec('insertUnorderedList')">• List</button>
            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-white hover:text-slate-900" title="Numbered list" @click="exec('insertOrderedList')">1. List</button>
            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-white hover:text-slate-900" title="Link" @click="insertLink">Link</button>
            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 transition hover:bg-white hover:text-slate-900" title="Remove formatting" @click="exec('removeFormat')">Clear</button>
        </div>
        <div
            ref="editor"
            class="rte-editor min-h-32 px-3 py-2.5 text-sm text-slate-800 outline-none empty:before:pointer-events-none empty:before:text-slate-400 empty:before:content-[attr(data-placeholder)] [&_a]:text-brand-600 [&_a]:underline [&_h2]:mb-2 [&_h2]:font-semibold [&_h2]:text-slate-900 [&_h3]:mb-2 [&_h3]:font-semibold [&_h3]:text-slate-900 [&_h4]:mb-2 [&_h4]:font-semibold [&_h4]:text-slate-900 [&_ol]:mb-2 [&_ol]:ml-5 [&_ol]:list-decimal [&_p]:mb-2 [&_p:last-child]:mb-0 [&_ul]:mb-2 [&_ul]:ml-5 [&_ul]:list-disc"
            :style="{ minHeight }"
            contenteditable="true"
            :data-placeholder="placeholder"
            @input="sync"
            @blur="sync"
            @paste="onPaste"
        />
    </div>
</template>
