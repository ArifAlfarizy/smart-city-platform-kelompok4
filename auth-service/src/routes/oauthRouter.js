import express from "express";
import { token, revoke } from "../controllers/oauthController.js";

const oauthRouter = express.Router();

oauthRouter.post("/token", token);
oauthRouter.post("/revoke", revoke);

export default oauthRouter;
