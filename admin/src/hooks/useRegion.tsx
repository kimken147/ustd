const useRegion = () => {
    const region = process.env["REACT_APP_REGION"];
    return region;
};

export default useRegion;
