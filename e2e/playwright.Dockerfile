FROM mcr.microsoft.com/playwright:v1.64.1-noble
WORKDIR /work
COPY package.json package-lock.json ./
RUN npm ci
COPY playwright.config.ts run-playwright.sh ./
COPY tests ./tests
ENTRYPOINT ["sh", "/work/run-playwright.sh"]
