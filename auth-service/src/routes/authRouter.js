import express from "express";
import { register } from "../controllers/authController.js";
import { token } from "../controllers/oauthController.js";

const authRouter = express.Router();

authRouter.post("/register", register);

authRouter.post("/login", (req, res, next) => {
  req.body = { ...req.body, grant_type: "password" };
  return token(req, res, next);
});
export default authRouter;
