import React from "react";
import { TitleProps } from "@refinedev/core";
import { Link } from "react-router-dom";

export const Title: React.FC<TitleProps> = ({ collapsed }) => (
    <Link to="/">
        {collapsed ? (
            <div
                style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                }}
            >
                {process.env.REACT_APP_LOGO_MINI_SRC ? (
                    <img
                        src={process.env.REACT_APP_LOGO_MINI_SRC}
                        alt="Refine"
                        style={{
                            margin: "0 auto",
                            padding: "12px 0",
                            maxHeight: "65.5px",
                        }}
                    />
                ) : null}
            </div>
        ) : (
            <img
                src={process.env.REACT_APP_LOGO_SRC}
                alt="Refine"
                style={{
                    width: "200px",
                    padding: "12px 24px",
                }}
            />
        )}
    </Link>
);
