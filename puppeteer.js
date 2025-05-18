const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({protocolTimeout: 10000});

    const page = await browser.newPage();

    const client = await page.target().createCDPSession();
    await client.send('Network.enable');
    await client.send('Network.setCacheDisabled', {cacheDisabled: true});
    await client.send('Network.setBypassServiceWorker', {bypass: true});

    const responseSizes = new Map();

    client.on('Network.requestWillBeSent', event => {
        const { requestId, request } = event;
        const { url, method } = request;

        responseSizes.set(requestId, {
            method,
            url,
        });
    });

    client.on('Network.responseReceived', async event => {
        const { requestId, response } = event;
        const { status } = response;
        const responseInfo = responseSizes.get(requestId);

        if (status === 204 || status === 404 || responseInfo.method === 'OPTIONS') {
            responseInfo.sizeCompressed = 0;
            responseInfo.sizeDecompressed = 0;
        }

        responseInfo.status = status;
    });

    client.on('Network.loadingFinished', async event => {
        const { requestId, encodedDataLength } = event;

        const responseInfo = responseSizes.get(requestId);

        if (responseInfo.method === 'OPTIONS') {
            return
        }

        const { body, base64Encoded } = await client.send('Network.getResponseBody', { requestId });

        const bodyBuffer = base64Encoded ? Buffer.from(body, 'base64'): Buffer.from(body);

        responseInfo.sizeCompressed = encodedDataLength;
        responseInfo.sizeDecompressed = bodyBuffer.length;
    });

    client.on('Network.loadingFailed', event => {
        const responseInfo = responseSizes.get(event.requestId);

        responseInfo.sizeCompressed = 0;
        responseInfo.sizeDecompressed = 0;
        responseInfo.status = event.errorText;
    });

    await page.goto(process.argv[1], { waitUntil: 'networkidle2' });

    await autoScroll(page);

    await page.waitForNetworkIdle({ idleTime: 1000 });

    const sortedResponses = [...responseSizes.values()]
        .sort((a, b) => b.sizeDecompressed - a.sizeDecompressed);

    let totalSize = 0;

    for (const response of sortedResponses) {
        if (process.argv[2] === '--debug') {
            console.log(`${formatBytes(response.sizeDecompressed)} / ${formatBytes(response.sizeCompressed)} | ${response.method} ${response.url} (${response.status})`);
        }

        totalSize += response.sizeDecompressed;
    }

    if (process.argv[2] === '--debug') {
        console.log(`\nTotal size: ${formatBytes(totalSize)}`);
    }

    process.stdout.write(totalSize.toString());

    await browser.close();
})();

async function autoScroll(page) {
    await page.evaluate(async () => {
        await new Promise((resolve) => {
            let totalHeight = 0;

            const timer = setInterval(() => {
                const scrollHeight = document.body.scrollHeight;
                window.scrollBy(0, window.innerHeight);
                totalHeight += window.innerHeight;

                if (totalHeight >= scrollHeight) {
                    clearInterval(timer);
                    window.scrollTo(0, 0);
                    resolve();
                }
            }, 100);
        });
    });
}

function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';

    const k = 1000;
    const sizes = ['Bytes', 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(decimals))} ${sizes[i]}`;
}