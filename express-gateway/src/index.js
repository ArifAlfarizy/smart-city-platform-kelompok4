import express from "express";
import { createProxyMiddleware } from "http-proxy-middleware";

const app = express();

app.use((req, res, next) => {
  console.log(req.method, req.url);
  next();
});

app.use(
  "/auth",
  createProxyMiddleware({
    target: "http://localhost:3002",
    changeOrigin: true,
  }),
);

app.listen(3000);
