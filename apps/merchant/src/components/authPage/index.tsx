import { AuthPageProps } from "@pankod/refine-core";
import { FC } from "react";
import LoginPage from "./login";

const AuthPage: FC<AuthPageProps> = () => {
    return <LoginPage />;
};

export default AuthPage;
