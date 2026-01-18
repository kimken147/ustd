import { useApiUrl, useCustomMutation } from "@refinedev/core";
import { ProviderUserChannel as UserChannel } from "@morgan-ustd/shared";

const useUserChannelMuation = () => {
    const apiUrl = useApiUrl();
    const { mutate, isPending: isLoading, isSuccess, isError } = useCustomMutation<UserChannel>();

    const mutateChannel = ({
        query,
        onSuccess,
        method,
    }: {
        query: Partial<IUpdateUserChannel>;
        onSuccess?: (data: UserChannel) => void;
        method?: "put" | "post" | "delete" | "patch";
    }) => {
        mutate(
            {
                url: `${apiUrl}/user-channel-accounts/${query.id}`,
                method: method || "put",
                values: query,
            },
            {
                onSuccess(data, variables, context) {
                    onSuccess && onSuccess(data.data);
                },
            },
        );
    };

    return {
        mutate: mutateChannel,
        isLoading,
        isSuccess,
        isError,
    };
};

export default useUserChannelMuation;
