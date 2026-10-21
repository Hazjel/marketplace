FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
ARG APP_TARGET=buyer
ENV VITE_APP_TARGET=$APP_TARGET \
    VITE_API_BASE_URL=/api \
    VITE_AI_SERVICE_URL=/ai \
    VITE_RECOMMENDATION_BASE_URL=/recommend \
    VITE_BUYER_APP_URL=http://127.0.0.1:18080 \
    VITE_SELLER_APP_URL=http://127.0.0.1:18081 \
    VITE_MIDTRANS_IS_PRODUCTION=false
RUN npm run build

FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
