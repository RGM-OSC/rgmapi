Summary:        API for the RGM suite.
Name:           rgmapi
Version:1.1
Release:        1.rgm
Source:         %{name}-%{version}.tar.gz
Source1:rgmapi.conf
Group:          Applications/System
License:        GPL
Vendor:         RGM Community
URL:            https://github.com/EyesOfNetworkCommunity/rgmapi
Requires:	rgmweb

BuildRoot:      %{_tmppath}/%{name}-%{version}-root

%define rgmdir          /srv/rgm
%define	datadir		%{rgmdir}/%{name}

%description
RGM includes a web-based "RESTful" API (Application Programming Interface) called RGMAPI that enables external programs to access information from the monitoring database and to manipulate objects inside the databases of RGM suite.

%prep
%setup -q -n %{name}-%{version}

%build

%install
install -d -m0755 %{buildroot}%{datadir}
install -d -m0755 %{buildroot}%{_sysconfdir}/httpd/conf.d
cp -afv ./* %{buildroot}%{datadir}
install -m 640 %{source1} %{buildroot}%{_sysconfdir}/httpd/conf.d/
rm -rf %{buildroot}%{datadir}/%{name}.spec

%post
systemctl restart httpd

%clean
rm -rf %{buildroot}

%files
%defattr(-,root,eyesofnetwork)
%{rgmdir}
%defattr(-,root,root)
%{_sysconfdir}/httpd/conf.d/rgmapi.conf

%changelog
* Mon Mar 04 2019 Michael Aubertin <maubertin@fr.scc.com> - 1.1-1
- Add CI.
- Prepare full fork.

* Mon Nov 28 2018 Michael Aubertin <michael.aubertin@gmail.com> - 1.0-4
- Initialize RGM fork from EON

* Thu Jun 14 2018 Jean-Philippe Levy <jeanphilippe.levy@gmail.com> - 1.0-3
- Add addEventBroker and delEventBroker functions

* Sun May 13 2018 Jean-Philippe Levy <jeanphilippe.levy@gmail.com> - 1.0-2
- Fix installation for EyesOfNetwork 5.2.

* Thu Oct 26 2017 Michael Aubertin <michael.aubertin@gmail.com> - 1.0-1
- Fix permission issue.

* Wed Oct 25 2017 Lucas Salinas - 1.0-0
-Package for EyesOfNetwork API.
