import { StrictMode } from "react"
import { renderToString } from "react-dom/server"
import App from "./App"

export function render(url = "/") {
  const path = url.split("?")[0] || "/"
  return renderToString(
    <StrictMode>
      <App path={path} />
    </StrictMode>,
  )
}
