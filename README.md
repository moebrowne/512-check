# 512KB Club Checker


## Run

1. Download sites list `curl https://raw.githubusercontent.com/kevquirk/512kb.club/refs/heads/main/_data/sites.yml -o sites.yml`
2. Run `php -f run.php sites.yml`



## How it works

1. Loop through all sites in the provided Yaml file
2. Skip any which have been updated in the last 24hrs
3. Run the `puppeteer.js` script in the [official Docker container](ghcr.io/puppeteer/puppeteer) which records all network traffic
4. Remove any sites which are dead or >512
5. Update the provided Yaml file

The Yaml file is updated as each site is checked so the process is resumable.



## Debugging

If you want to see the full requests for a site and any error messages, a debug flag can be passed:

```bash
docker run -i --init --cap-add=SYS_ADMIN --rm ghcr.io/puppeteer/puppeteer:latest node -e "$(cat puppeteer.js)" https://www.mountainofcode.co.uk/ --debug
```

```
73.55 KB / 6.56 KB | GET https://www.mountainofcode.co.uk/ (200)
34.85 KB / 34.91 KB | GET https://www.mountainofcode.co.uk/assets/fonts/ubuntu-v20-latin-regular.woff2 (200)
13.5 KB / 13.56 KB | GET https://www.mountainofcode.co.uk/assets/fonts/share-tech-mono-v15-latin-regular.woff2 (200)
8.45 KB / 2.44 KB | GET https://www.mountainofcode.co.uk/assets/style.css (200)
5.97 KB / 1.96 KB | GET https://www.mountainofcode.co.uk/assets/hexGrid.js (200)
1.16 KB / 495 Bytes | GET https://www.mountainofcode.co.uk/assets/code-style.css (200)
826 Bytes / 420 Bytes | GET https://www.mountainofcode.co.uk/assets/icon.svg (200)
558 Bytes / 395 Bytes | GET https://www.mountainofcode.co.uk/assets/rss.svg (200)

Total size: 138.87 KB
138868
```
