const CryptoJS = require('crypto-js');

function ksort(o) {
    return Object.keys(o).sort().reduce((prev, cur) => {
        prev[cur] = o[cur];
        return prev;
    }, {})
}

function serialize(obj) {
    var str = [];
    for (var p in obj)
        if (obj.hasOwnProperty(p) && obj[p] !== '' && obj[p] !== null && obj[p] !== undefined) {
            str.push(encodeURIComponent(p) + "=" + obj[p]);
        }
    return str.join("&");
}

function generateRandomOrderNumber() {
    var randomNumber = '';
    for (var i = 0; i < 10; i++) {
        randomNumber += Math.floor(Math.random() * 10);
    }
    return 'test' + randomNumber;
}

const body = JSON.parse(pm.request.body);

if (!body.out_trade_no) {
    body.out_trade_no = generateRandomOrderNumber();
} else {
    body.out_trade_no = generateRandomOrderNumber();
}

// Remove empty values before signing (empty values don't participate in signature)
const signBody = {};
for (const [k, v] of Object.entries(body)) {
    if (v !== '' && v !== null && v !== undefined) {
        signBody[k] = v;
    }
}

const query = serialize(ksort(signBody));
body.sign = CryptoJS.MD5(`${query}&secret_key=${pm.environment.get("merchant_secret")}`).toString();
console.log(body);
pm.request.body.raw = JSON.stringify(body);
