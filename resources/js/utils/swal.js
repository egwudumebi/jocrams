import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const institutional = '#0a3d91';
const danger = '#dc2626';

/**
 * @param {object} options
 * @param {string} options.title
 * @param {string} [options.text]
 * @param {string} [options.confirmText]
 * @param {string} [options.cancelText]
 * @param {'primary'|'danger'} [options.tone]
 * @returns {Promise<boolean>}
 */
export async function confirmAction({
    title,
    text = '',
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    tone = 'primary',
} = {}) {
    const result = await Swal.fire({
        title,
        text,
        icon: tone === 'danger' ? 'warning' : 'question',
        showCancelButton: true,
        focusCancel: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor: tone === 'danger' ? danger : institutional,
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        customClass: {
            popup: 'rounded-2xl',
            confirmButton: '!rounded-xl !px-5 !py-2.5 !font-semibold',
            cancelButton: '!rounded-xl !px-5 !py-2.5 !font-semibold',
        },
    });

    return result.isConfirmed;
}

/**
 * @param {string} title
 * @param {string} [text]
 */
export async function notifySuccess(title, text = '') {
    await Swal.fire({
        title,
        text,
        icon: 'success',
        confirmButtonColor: institutional,
        confirmButtonText: 'OK',
        timer: 2800,
        timerProgressBar: true,
        customClass: {
            popup: 'rounded-2xl',
            confirmButton: '!rounded-xl !px-5 !py-2.5 !font-semibold',
        },
    });
}

/**
 * @param {string} title
 * @param {string} [text]
 */
export async function notifyError(title, text = '') {
    await Swal.fire({
        title,
        text,
        icon: 'error',
        confirmButtonColor: danger,
        confirmButtonText: 'OK',
        customClass: {
            popup: 'rounded-2xl',
            confirmButton: '!rounded-xl !px-5 !py-2.5 !font-semibold',
        },
    });
}
