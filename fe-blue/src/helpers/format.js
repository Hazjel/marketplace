import numeral from 'numeral';
import { DateTime } from 'luxon';

export function formatRupiah(value) {
    return numeral(value).format('0,0[.]00')
}

export function parseRupiah(value) {
    return numeral(value).value();
}

export function formatPercentage(value) {
    return numeral(value).format('0,0[.]00%');
}

export function formatDate(date) {
    const options = { day : 'numeric', month : 'long', year : 'numeric' };
    return new Date(date).toLocaleDateString('id-ID', options);
}

export function formatDateTime(dateTime) {
    const options = { day : 'numeric', month : 'long', year : 'numeric', hour: 'numeric', minute: 'numeric' };
    return new Date(dateTime).toLocaleDateString('id-ID', options);
}

export function formatToClientTimeZone(date){
    const originalDate = DateTime.fromISO(date, { zone: 'utc' });

    const timezone = intl.DateTimeFormat().resolvedOptions().timeZone;

    return originalDate
        .setZone(timezone)
        .setLocale('id')
        .toFormat('dd LLLL yyyy, HH:mm');
}

export function ucFirst(string){
    return string ? string.charAt(0).toUpperCase() + string.slice(1) : '';
}