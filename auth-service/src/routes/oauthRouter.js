import express from "express";
import { token, revoke, introspect } from "../controllers/oauthController.js";

const oauthRouter = express.Router();

oauthRouter.post("/token", token);
oauthRouter.post("/revoke", revoke);
oauthRouter.post("/introspect", introspect);

export default oauthRouter;
