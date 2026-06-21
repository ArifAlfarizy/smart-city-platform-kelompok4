import express from "express";
import { createProxyMiddleware } from "http-proxy-middleware";
import verifyToken from "./middleware/verifyToken.js";
import { limiter } from "./middleware/rateLimit.js";
import { requestLogger } from "./middleware/logger.js";

const app = express();
app.use(requestLogger);

app.use(
  "/oauth/revoke",
  limiter,
  createProxyMiddleware({
    target: "http://localhost:3002",
    changeOrigin: true,
  }),
);

// auth service
app.use(
  ["/auth", "/oauth"],
  createProxyMiddleware({
    target: "http://localhost:3002",
    changeOrigin: true,
  }),
);

app.listen(3000);
