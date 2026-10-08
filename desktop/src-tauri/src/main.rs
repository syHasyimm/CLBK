#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

mod server;

use std::time::Duration;
use tauri::{webview::NewWindowResponse, Manager, WebviewUrl, WebviewWindowBuilder};

fn reachable(url: &url::Url) -> bool {
    let Ok(client) = reqwest::blocking::Client::builder()
        .timeout(Duration::from_secs(8))
        .redirect(reqwest::redirect::Policy::none())
        .build()
    else {
        return false;
    };
    let Ok(health) = url.join("up") else {
        return false;
    };
    client
        .get(health)
        .send()
        .map(|response| response.status().is_success())
        .unwrap_or(false)
}

fn main() {
    tauri::Builder::default()
        .setup(|app| {
            let config_file = app.path().app_config_dir()?.join("server.json");
            match server::resolve(&config_file) {
                Ok(url) if reachable(&url) => {
                    let origin = url.clone();
                    WebviewWindowBuilder::new(app, "main", WebviewUrl::External(url))
                        .title("CLBK TOKE")
                        .inner_size(1280.0, 820.0)
                        .min_inner_size(800.0, 600.0)
                        .on_navigation(move |destination| server::same_origin(&origin, destination))
                        .on_new_window(|_, _| NewWindowResponse::Deny)
                        .build()?;
                }
                result => {
                    if let Err(error) = result {
                        eprintln!("{error}");
                    }
                    WebviewWindowBuilder::new(
                        app,
                        "connection",
                        WebviewUrl::App("index.html".into()),
                    )
                    .title("CLBK TOKE — Koneksi server")
                    .inner_size(800.0, 600.0)
                    .on_new_window(|_, _| NewWindowResponse::Deny)
                    .build()?;
                }
            }
            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("Aplikasi desktop CLBK TOKE tidak dapat dijalankan.");
}
