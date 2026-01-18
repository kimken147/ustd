class EnviromentSingleton {
    public isPaufen: boolean;
    constructor() {
        this.isPaufen = process.env["REACT_APP_IS_PAUFEN"] === "true" ? true : false;
    }
    getVariable(name: string) {
        return process.env[`REACT_APP_${name}`];
    }
}

const Enviroment = new EnviromentSingleton();
export default Enviroment;
