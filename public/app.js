(() => {

const getElementById = e => document.getElementById(e);
const padStart20 = e => `${e}`.padStart(2, 0);

const $room = getElementById('room');
const $type = getElementById('type');
const $query = getElementById('query');
const $version = getElementById('version');

const queryUrls = [
    r => `api/billing/${r}?format=markdown`,
    r => `api/payment-record/${r}?format=markdown`,
    (r, d) => `api/metrical-data/${r}/${d.getFullYear()}-${padStart20(d.getMonth() + 1)}?format=markdown`,
    (r, d) => `api/metrical-data/${r}/${d.getFullYear()}?format=markdown`,
    (r, d) => `api/metrical-data/${r}/${d.getFullYear()}-${padStart20(d.getMonth() + 1)}?format=chart`,
    (r, d) => `api/metrical-data/${r}/${d.getFullYear()}?format=chart`,
];
const updateQueryUrl = () => $room.value && ($query.href = $query.innerText = `${location.protocol}//${location.host}${location.pathname}${queryUrls[parseInt($type.value)]($room.value, new Date)}`);
$room.oninput = $type.oninput = updateQueryUrl;
updateQueryUrl();

fetch('api/version')
    .then(res => res.json())
    .then(res => {
        const result = res.result;
        if (result.commit && result.commitShort && result.commitTime) {
            const commitTime = new Date(1000 * result.commitTime);
            $version.parentElement.style.cssText = $version.parentElement.nextElementSibling.cssText = '';
            $version.innerText = result.commitShort;
            $version.title = `${result.commit} (${commitTime.getFullYear()}-${padStart20(commitTime.getMonth() + 1)}-${padStart20(commitTime.getDate())} ${padStart20(commitTime.getHours())}:${padStart20(commitTime.getMinutes())}:${padStart20(commitTime.getSeconds())})`;
        }
    });

})()