import express from "express";
import { createProxyMiddleware } from "http-proxy-middleware";
import verifyToken from "./middleware/verifyToken.js";

const app = express();

app.use((req, res, next) => {
  console.log(req.method, req.url);
  next();
});


// auth service
app.use(
  ["/auth", "/oauth"],
  createProxyMiddleware({
    target: "http://localhost:3002",
    changeOrigin: true,
  }),
);

app.use(
  "/oauth/revoke",
  verifyToken,
  createProxyMiddleware({
    target: "http://localhost:3002",
    changeOrigin: true,
  }),
);

app.listen(3000);
