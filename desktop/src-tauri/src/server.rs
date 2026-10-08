use serde::Deserialize;
use std::{fs, path::Path};
use url::Url;

#[derive(Deserialize)]
struct ServerConfig {
    server_url: String,
}

pub fn validate(raw: &str, development: bool) -> Result<Url, String> {
    let url = Url::parse(raw).map_err(|_| "Alamat server tidak valid.".to_string())?;
    if url.host_str().is_none()
        || !url.username().is_empty()
        || url.password().is_some()
        || url.query().is_some()
        || url.fragment().is_some()
        || url.path() != "/"
    {
        return Err(
            "Gunakan alamat origin server tanpa kredensial, path, query, atau fragment.".into(),
        );
    }
    let loopback = matches!(url.host_str(), Some("localhost" | "127.0.0.1" | "[::1]"));
    if url.scheme() != "https" && !(development && loopback && url.scheme() == "http") {
        return Err(
            "Server wajib menggunakan HTTPS; HTTP localhost hanya untuk development.".into(),
        );
    }
    Ok(url)
}

pub fn resolve(config_file: &Path) -> Result<Url, String> {
    let raw = match std::env::var("CLBK_SERVER_URL") {
        Ok(value) => value,
        Err(_) if config_file.is_file() => {
            let content = fs::read_to_string(config_file)
                .map_err(|_| "Konfigurasi server tidak dapat dibaca.")?;
            let config: ServerConfig = serde_json::from_str(&content)
                .map_err(|_| "Format konfigurasi server tidak valid.")?;
            config.server_url
        }
        Err(_) if cfg!(debug_assertions) => "http://127.0.0.1:8000".to_string(),
        Err(_) => return Err("Alamat server belum dikonfigurasi.".into()),
    };
    validate(&raw, cfg!(debug_assertions))
}

pub fn same_origin(server: &Url, destination: &Url) -> bool {
    server.origin() == destination.origin()
        && destination.username().is_empty()
        && destination.password().is_none()
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn production_accepts_https_and_rejects_http() {
        assert!(validate("https://toke.example.com", false).is_ok());
        assert!(validate("http://127.0.0.1:8000", false).is_err());
        assert!(validate("http://192.168.1.10:8000", true).is_err());
        assert!(validate("http://127.0.0.1:8000", true).is_ok());
    }

    #[test]
    fn credentials_and_non_origin_urls_are_rejected() {
        for value in [
            "https://user:pass@toke.example.com",
            "https://toke.example.com/path",
            "https://toke.example.com?token=1",
            "file:///C:/data",
        ] {
            assert!(validate(value, false).is_err());
        }
    }

    #[test]
    fn navigation_is_restricted_to_the_exact_server_origin() {
        let server = validate("https://toke.example.com", false).unwrap();
        assert!(same_origin(
            &server,
            &Url::parse("https://toke.example.com/login").unwrap()
        ));
        assert!(!same_origin(
            &server,
            &Url::parse("https://toke.example.com.attacker.test/login").unwrap()
        ));
        assert!(!same_origin(
            &server,
            &Url::parse("https://toke.example.com:8443/login").unwrap()
        ));
        assert!(!same_origin(
            &server,
            &Url::parse("http://toke.example.com/login").unwrap()
        ));
    }
}
