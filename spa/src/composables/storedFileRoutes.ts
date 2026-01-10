import { apiUrls } from "@/api";
import { useActionRoutes } from "quasar-ui-danx";

export const storedFileRoutes = useActionRoutes(apiUrls.fileUpload.storedFiles);
