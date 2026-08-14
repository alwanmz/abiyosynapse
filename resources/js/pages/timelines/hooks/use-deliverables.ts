export function useDeliverables(
    deliverables: string[],
    setFormData: React.Dispatch<React.SetStateAction<any>>
) {
    const handleDeliverableChange = (index: number, value: string) => {
        const newDeliverables = [...deliverables];
        newDeliverables[index] = value;
        setFormData((prev: any) => ({ ...prev, deliverables: newDeliverables }));
    };

    const addDeliverable = () => {
        setFormData((prev: any) => ({
            ...prev,
            deliverables: [...prev.deliverables, ''],
        }));
    };

    const removeDeliverable = (index: number) => {
        setFormData((prev: any) => ({
            ...prev,
            deliverables: prev.deliverables.filter((_: any, i: number) => i !== index),
        }));
    };

    return {
        handleDeliverableChange,
        addDeliverable,
        removeDeliverable,
    };
}
