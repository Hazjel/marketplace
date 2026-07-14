FROM node:20-alpine

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# Build saat image build (tanpa bind mount Windows yang lambat).
# Vite membaca VITE_* dari env, jadi di-inject via build args.
ARG VITE_APP_NAME
ARG VITE_API_BASE_URL
ARG VITE_AI_SERVICE_URL
ARG VITE_MIDTRANS_CLIENT_KEY
ARG VITE_MIDTRANS_IS_PRODUCTION
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME

RUN npm run build -- --outDir /build-out

EXPOSE 5173

# /app di-override bind mount oleh compose; hasil build disalin ke volume fe_dist (/app/dist)
CMD ["sh", "-c", "rm -rf /app/dist/* && cp -r /build-out/. /app/dist/ && echo '✅ Vue build deployed to fe_dist' && tail -f /dev/null"]
