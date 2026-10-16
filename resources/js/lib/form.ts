export const operationError = (errors: object) => (errors as Record<string, string>).operation;
